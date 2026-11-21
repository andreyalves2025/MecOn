/**
 * MecOn - chat.js
 * Chat entre cliente e oficina, compartilhado pelos dois perfis.
 *
 *   iniciarChat(raiz, { como: 'usuario'|'oficina', abrirOficina: { id, nome } })
 *     desenha a lista de conversas + a conversa aberta dentro de `raiz`.
 *     `abrirOficina` (só para usuário) abre/cria a conversa com aquela oficina.
 *   monitorarMensagensNaoLidas(como)
 *     consulta periodicamente o total de não lidas e mostra no rótulo da
 *     aba "Mensagens".
 *
 * Sem WebSocket: a conversa aberta consulta mensagens novas a cada 5 s, e a
 * lista de conversas é renovada a cada 15 s. Os temporizadores se encerram
 * sozinhos quando a tela do chat sai do DOM (troca de aba).
 * Depende de auth.js (chamarApi, _escHtml).
 */
const CHAT_INTERVALO_THREAD_MS = 5000;
const CHAT_INTERVALO_LISTA_MS = 15000;

function _chatHora(dataIso) {
  const d = new Date(dataIso.replace(' ', 'T'));
  const hoje = new Date();
  const mesmoDia = d.toDateString() === hoje.toDateString();
  return mesmoDia
    ? d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
    : d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function atualizarRotuloAbaMensagens(quantidade) {
  const aba = document.querySelector('.perfil-aba[data-aba="mensagens"]');
  if (!aba) return;
  if (!aba.dataset.rotuloOriginal) aba.dataset.rotuloOriginal = aba.textContent.trim();
  aba.textContent = quantidade > 0 ? `${aba.dataset.rotuloOriginal} (${quantidade})` : aba.dataset.rotuloOriginal;
}

function monitorarMensagensNaoLidas(como) {
  const consultar = async () => {
    try {
      const r = await chamarApi(`/chat/contagem_nao_lidas.php?como=${como}`);
      atualizarRotuloAbaMensagens(r.nao_lidas);
    } catch (e) { /* sessão expirada ou rede: tenta de novo no próximo ciclo */ }
  };
  consultar();
  setInterval(consultar, 30000);
}

function iniciarChat(raiz, opcoes) {
  const como = opcoes.como;
  let abrirOficina = opcoes.abrirOficina || null;
  let conversaAtual = null;     // { id_conversa|null, id_oficina?, nome }
  let ultimoId = 0;
  let lidasAte = 0;
  let timerThread = null;
  let timerLista = null;

  raiz.innerHTML = `
    <div style="display:flex; gap:14px; flex-wrap:wrap; align-items:flex-start;">
      <div class="chat-lista" style="flex:1 1 220px; max-width:320px; min-width:200px;"></div>
      <div class="chat-thread" style="flex:2 1 320px; min-width:280px;"></div>
    </div>`;
  const elLista = raiz.querySelector('.chat-lista');
  const elThread = raiz.querySelector('.chat-thread');

  const vivo = () => document.body.contains(raiz);
  function encerrar() {
    clearInterval(timerThread);
    clearInterval(timerLista);
  }

  /* ---------- lista de conversas ---------- */
  async function carregarConversas(primeiraVez = false) {
    try {
      const r = await chamarApi(`/chat/list_conversas.php?como=${como}`);
      desenharLista(r.conversas);
      atualizarRotuloAbaMensagens(r.conversas.reduce((soma, c) => soma + c.nao_lidas, 0));
      if (primeiraVez) {
        if (abrirOficina) {
          const existente = r.conversas.find((c) => c.id_oficina === abrirOficina.id);
          abrirConversa(existente
            ? { id_conversa: existente.id_conversa, id_oficina: existente.id_oficina, nome: existente.nome_outro }
            : { id_conversa: null, id_oficina: abrirOficina.id, nome: abrirOficina.nome || 'Oficina' });
          abrirOficina = null;
        } else if (r.conversas.length === 0) {
          elThread.innerHTML = como === 'usuario'
            ? '<p style="color: var(--texto-secundario);">Você ainda não iniciou nenhuma conversa. Abra a página de uma oficina e use "Enviar mensagem".</p>'
            : '<p style="color: var(--texto-secundario);">Nenhum cliente enviou mensagem ainda.</p>';
        }
      }
    } catch (erro) {
      elLista.innerHTML = `<p>Não foi possível carregar as conversas: ${_escHtml(erro.message)}</p>`;
    }
  }
  function desenharLista(conversas) {
    if (conversas.length === 0) {
      elLista.innerHTML = '<p style="color: var(--texto-secundario); font-size:0.9rem;">Sem conversas.</p>';
      return;
    }
    elLista.innerHTML = conversas.map((c) => `
      <button type="button" class="item-conversa" data-id="${c.id_conversa}"
        style="display:block; width:100%; text-align:left; background:${conversaAtual && conversaAtual.id_conversa === c.id_conversa ? 'var(--lilas-claro)' : '#fff'}; border:1px solid var(--borda); border-radius: var(--raio-botao); padding:10px 12px; margin-bottom:8px; cursor:pointer;">
        <div style="display:flex; justify-content:space-between; gap:8px;">
          <strong>${_escHtml(c.nome_outro)}</strong>
          ${c.nao_lidas > 0 ? `<span style="background:#C0392B; color:#fff; border-radius:999px; font-size:0.7rem; padding:1px 7px; font-weight:700;">${c.nao_lidas}</span>` : ''}
        </div>
        <div style="font-size:0.8rem; color: var(--texto-secundario); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
          ${_escHtml(c.ultima_mensagem || '')}
        </div>
      </button>`).join('');
    elLista.querySelectorAll('.item-conversa').forEach((b) => {
      b.addEventListener('click', () => {
        const c = conversas.find((x) => x.id_conversa === Number(b.dataset.id));
        abrirConversa({ id_conversa: c.id_conversa, id_oficina: c.id_oficina, nome: c.nome_outro });
      });
    });
  }

  /* ---------- conversa aberta ---------- */
  function abrirConversa(conv) {
    conversaAtual = conv;
    ultimoId = 0;
    lidasAte = 0;
    clearInterval(timerThread);
    elThread.innerHTML = `
      <div style="border:1px solid var(--borda); border-radius: var(--raio-botao); overflow:hidden;">
        <div style="padding:10px 14px; background: var(--lilas-claro); font-weight:600;">${_escHtml(conv.nome)}</div>
        <div class="chat-mensagens" style="height:340px; overflow-y:auto; padding:12px; display:flex; flex-direction:column; gap:6px; background:#fff;"></div>
        <form class="chat-form" style="display:flex; gap:8px; padding:10px; border-top:1px solid var(--borda);">
          <textarea class="chat-texto" rows="1" maxlength="2000" placeholder="Escreva uma mensagem…"
            style="flex:1; resize:none; padding:8px 10px; border:1px solid var(--borda); border-radius: var(--raio-botao); font-family: var(--fonte-corpo);"></textarea>
          <button type="submit" class="botao botao--primario" style="padding:8px 16px;">Enviar</button>
        </form>
        <p class="mensagem-erro chat-erro" style="margin:0 10px 10px;"></p>
      </div>`;
    const form = elThread.querySelector('.chat-form');
    const campo = elThread.querySelector('.chat-texto');
    campo.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        form.requestSubmit();
      }
    });
    form.addEventListener('submit', async (evento) => {
      evento.preventDefault();
      const texto = campo.value.trim();
      if (!texto) return;
      const erroEl = elThread.querySelector('.chat-erro');
      erroEl.classList.remove('mensagem-erro--visivel');
      try {
        const corpo = { como, texto };
        if (conversaAtual.id_conversa) corpo.id_conversa = conversaAtual.id_conversa;
        else corpo.id_oficina = conversaAtual.id_oficina;
        const r = await chamarApi('/chat/enviar_mensagem.php', { method: 'POST', body: JSON.stringify(corpo) });
        campo.value = '';
        const eraNova = !conversaAtual.id_conversa;
        conversaAtual.id_conversa = r.id_conversa;
        anexarMensagens([{ ...r.mensagem, id_mensagem: Number(r.mensagem.id_mensagem) }]);
        if (eraNova) carregarConversas();
      } catch (erro) {
        erroEl.textContent = erro.message;
        erroEl.classList.add('mensagem-erro--visivel');
      }
    });
    if (conv.id_conversa) {
      carregarMensagens();
      timerThread = setInterval(() => {
        if (!vivo()) { encerrar(); return; }
        carregarMensagens();
      }, CHAT_INTERVALO_THREAD_MS);
    }
    campo.focus();
  }

  async function carregarMensagens() {
    if (!conversaAtual || !conversaAtual.id_conversa) return;
    try {
      const r = await chamarApi(
        `/chat/list_mensagens.php?como=${como}&id_conversa=${conversaAtual.id_conversa}&depois_de=${ultimoId}`
      );
      lidasAte = r.lidas_ate;
      anexarMensagens(r.mensagens);
      marcarVisualizadas();
      if (r.mensagens.length > 0) carregarConversas(); // zera o contador de não lidas na lista e na aba
    } catch (erro) { /* falha pontual de rede: tenta de novo no próximo ciclo */ }
  }

  function anexarMensagens(mensagens) {
    const caixa = elThread.querySelector('.chat-mensagens');
    if (!caixa) return;
    const nofim = caixa.scrollHeight - caixa.scrollTop - caixa.clientHeight < 60;
    let anexou = false;
    mensagens.forEach((m) => {
      if (m.id_mensagem <= ultimoId) return; // já exibida (ex.: enviada agora e devolvida pelo polling)
      ultimoId = m.id_mensagem;
      const minha = m.remetente === como;
      caixa.insertAdjacentHTML('beforeend', `
        <div data-id="${m.id_mensagem}" data-minha="${minha ? '1' : '0'}" style="align-self:${minha ? 'flex-end' : 'flex-start'}; max-width:80%;">
          <div style="background:${minha ? 'var(--azul-mecon)' : '#EEF0F3'}; color:${minha ? '#fff' : 'inherit'}; padding:8px 12px; border-radius:12px; white-space:pre-wrap; word-break:break-word;">${_escHtml(m.texto)}</div>
          <div class="chat-meta" style="font-size:0.7rem; color: var(--texto-secundario); text-align:${minha ? 'right' : 'left'}; margin-top:2px;">${_chatHora(m.criado_em)}<span class="chat-visto"></span></div>
        </div>`);
      anexou = true;
    });
    if (anexou && nofim) caixa.scrollTop = caixa.scrollHeight;
    marcarVisualizadas();
  }
  function marcarVisualizadas() {
    elThread.querySelectorAll('[data-minha="1"]').forEach((el) => {
      const visto = el.querySelector('.chat-visto');
      if (visto) visto.textContent = Number(el.dataset.id) <= lidasAte ? ' · visualizada' : '';
    });
  }

  carregarConversas(true);
  timerLista = setInterval(() => {
    if (!vivo()) { encerrar(); return; }
    carregarConversas();
  }, CHAT_INTERVALO_LISTA_MS);
}
