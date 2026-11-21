/**
 * MecOn - oficina.js
 * Página de detalhe de uma oficina: dados completos, lista de avaliações,
 * botão de favoritar (toggle) e formulário de novo comentário.
 *
 * Regras de fluxo (conforme especificado):
 *  - Deslogado clicando em "favoritar" ou "comentar" -> redireciona para login.html
 *  - Logado pode favoritar/desfavoritar (toggle) e enviar comentário com nota.
 */
const params = new URLSearchParams(window.location.search);
const idOficina = params.get('id');
const container = document.getElementById('conteudo-oficina');
let favoritoAtual = false;
function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
}
function estrelas(nota) {
    const cheias = Math.round(nota);
    return '★★★★★☆☆☆☆☆'.slice(5 - cheias, 10 - cheias);
}
function formatarDataBR(dataIso) {
    const [ano, mes, dia] = dataIso.split('-');
    return `${dia}/${mes}/${ano}`;
}
function mesesDesde(dataIso) {
    const inicio = new Date(dataIso.replace(' ', 'T'));
    const agora = new Date();
    let meses = (agora.getFullYear() - inicio.getFullYear()) * 12 + (agora.getMonth() - inicio.getMonth());
    return Math.max(meses, 0);
}
function renderizarOficina(oficina, avaliacoes, usuario, promocoes) {
    const nota = oficina.media_avaliacao !== null ? oficina.media_avaliacao.toFixed(1) : 'Sem avaliações';
    const hc = oficina.historico_confianca;
    const blocoConfianca = hc ? `
      <div style="display:flex; flex-wrap:wrap; gap:8px; margin: 10px 0;">
        ${hc.verificada_desde ? `<span class="tag-servico">✔ Verificada há ${mesesDesde(hc.verificada_desde)} meses</span>` : ''}
        ${hc.servicos_concluidos > 0 ? `<span class="tag-servico">${hc.servicos_concluidos} serviço(s) concluído(s) pelo MecOn</span>` : ''}
        ${hc.avaliacoes_verificadas > 0 ? `<span class="tag-servico">${hc.avaliacoes_verificadas} avaliação(ões) verificada(s)</span>` : ''}
      </div>
    ` : '';
    const blocoPromocoes = promocoes.length ? `
      <div style="margin: 12px 0; display:flex; flex-direction:column; gap:8px;">
        ${promocoes.map((p) => `
          <div style="padding:10px 14px; background:#FFF4E5; border-left:3px solid #E8A33D; border-radius: var(--raio-botao);">
            <p style="font-weight:700; margin:0 0 2px;">🏷️ ${escapeHtml(p.titulo)}</p>
            ${p.descricao ? `<p style="margin:0 0 2px; font-size:0.9rem;">${escapeHtml(p.descricao)}</p>` : ''}
            <p style="margin:0; font-size:0.8rem; color: var(--texto-secundario);">
              Válida até ${formatarDataBR(p.data_fim)}${p.nome_servico ? ' · ' + escapeHtml(p.nome_servico) : ''}
            </p>
          </div>
        `).join('')}
      </div>
    ` : '';
    const tagsServico = oficina.servicos
        .map((s) => `<span class="tag-servico">${escapeHtml(s.nome)} — R$ ${s.preco.toFixed(2)}</span>`)
        .join('');
    const listaAvaliacoes = avaliacoes.length
        ? avaliacoes.map((a) => `
        <div class="avaliacao">
          <div class="avaliacao__avatar" style="display:flex; align-items:center; justify-content:center; color: ${obterCorAvatar(a.nome_usuario)}; font-weight:700; font-size:0.9rem;">${obterIniciais(a.nome_usuario)}</div>
          <div class="avaliacao__corpo">
            <div class="avaliacao__cabecalho">
              <span class="avaliacao__nome">
                ${escapeHtml(a.nome_usuario)}
                ${a.verificada ? '<span title="Este cliente contratou o serviço pelo MecOn" style="color: var(--verde-sucesso); font-weight:600; font-size:0.78rem; margin-left:6px;">✔ Serviço confirmado pelo MecOn</span>' : ''}
              </span>
              <span class="avaliacao__estrelas">${estrelas(a.nota)}</span>
            </div>
            <p class="avaliacao__comentario">${escapeHtml(a.comentario || '')}</p>
            ${a.resposta_oficina ? `
              <div style="margin-top:10px; padding:10px 14px; background: var(--lilas-claro); border-radius: var(--raio-botao); border-left: 3px solid var(--azul-mecon);">
                <p style="font-weight:600; font-size:0.85rem; margin:0 0 4px;">Resposta de ${escapeHtml(oficina.nome)}</p>
                <p style="margin:0; font-size:0.9rem;">${escapeHtml(a.resposta_oficina)}</p>
              </div>
            ` : ''}
            ${usuario && usuario.id_usuario !== a.id_usuario ? `
              <button type="button" class="botao-denunciar" data-id="${a.id_avaliacao}"
                style="margin-top:8px; background:none; border:none; padding:0; color: var(--texto-secundario); font-size:0.8rem; text-decoration:underline; cursor:pointer;">
                Denunciar avaliação
              </button>
            ` : ''}
          </div>
        </div>
      `).join('')
        : '<p style="color: var(--texto-secundario);">Ainda não há avaliações para esta oficina.</p>';
    const botaoAvaliar = usuario
        ? '<button type="button" id="botao-avaliar" class="botao botao--primario" style="margin-top: 16px;">Avaliar oficina</button>'
        : `<a href="login.html?retorno=${encodeURIComponent('oficina.html?id=' + idOficina + '&acao=avaliar')}" class="botao botao--primario" style="margin-top: 16px;">Avaliar oficina</a>`;
    const botaoAgendar = usuario
        ? '<button type="button" id="botao-agendar" class="botao botao--primario" style="margin-top: 16px;">Solicitar agendamento</button>'
        : `<a href="login.html?retorno=${encodeURIComponent('oficina.html?id=' + idOficina)}" class="botao botao--primario" style="margin-top: 16px;">Solicitar agendamento</a>`;
    const botaoOrcamento = usuario
        ? '<button type="button" id="botao-orcamento" class="botao botao--secundario" style="margin-top: 16px;">Solicitar orçamento</button>'
        : `<a href="login.html?retorno=${encodeURIComponent('oficina.html?id=' + idOficina)}" class="botao botao--secundario" style="margin-top: 16px;">Solicitar orçamento</a>`;
    const botaoMensagem = usuario
        ? `<a href="perfil.html?aba=mensagens&oficina=${oficina.id_oficina}&nome=${encodeURIComponent(oficina.nome)}" class="botao botao--secundario" style="margin-top: 16px;">Enviar mensagem</a>`
        : `<a href="login.html?retorno=${encodeURIComponent('oficina.html?id=' + idOficina)}" class="botao botao--secundario" style="margin-top: 16px;">Enviar mensagem</a>`;
    container.innerHTML = `
    <div class="card-oficina" style="margin-bottom: 24px;">
      <img src="../img/oficina.jpg" alt="Foto da oficina ${escapeHtml(oficina.nome)}" class="oficina-foto">
      <div class="card-oficina__info">
        <div class="card-oficina__nome" style="font-size: 1.4rem;">${escapeHtml(oficina.nome)}
          ${oficina.verificada ? '<span title="Oficina verificada pela equipe MecOn" style="color: var(--azul-mecon); font-size:1.1rem; margin-left:6px;">✔ Verificada</span>' : ''}
          <span class="card-oficina__avaliacao" style="margin-left: 10px; font-size: 1.1rem;">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 20px; height: 20px;"><path d="M9.05 2.93a1 1 0 011.9 0l1.36 2.77 3.06.45a1 1 0 01.55 1.7l-2.21 2.15.52 3.05a1 1 0 01-1.45 1.05L10 12.7l-2.78 1.4a1 1 0 01-1.45-1.05l.52-3.05-2.21-2.15a1 1 0 01.55-1.7l3.06-.45z"/></svg>
            ${nota}
          </span>
        </div>
        <p class="card-oficina__descricao">${escapeHtml(oficina.descricao || 'Esta oficina ainda não adicionou uma descrição.')}</p>
        ${blocoConfianca}
        ${montarBlocoReputacaoHTML(oficina.reputacao)}
        ${blocoPromocoes}
        <p class="oficina-dado"><strong>Localização</strong> ${escapeHtml(oficina.endereco)}, ${escapeHtml(oficina.bairro)}</p>
        <p class="oficina-dado"><strong>Telefone</strong> ${escapeHtml(oficina.telefone)}</p>
        <div class="oficina-servicos"><strong>Serviços</strong><div class="card-oficina__servicos">${tagsServico}</div></div>
        <div class="acoes-oficina">
          <button type="button" id="botao-favoritar" class="botao botao--secundario">
            ${favoritoAtual ? '♥ Remover dos favoritos' : '♡ Favoritar oficina'}
          </button>
          ${botaoAvaliar}
          ${botaoAgendar}
          ${botaoMensagem}
          ${botaoOrcamento}
        </div>
        <div id="bloco-agendamento" style="margin-top: 16px;"></div>
        <div id="bloco-orcamento" style="margin-top: 16px;"></div>
      </div>
    </div>
    <h2>Avaliações</h2>
    <div class="lista-avaliacoes">${listaAvaliacoes}</div>
    <div id="bloco-novo-comentario" style="margin-top: 24px;"></div>
  `;
    renderizarBlocoComentario(usuario);
    document.getElementById('botao-favoritar').addEventListener('click', alternarFavorito);
    document.querySelectorAll('.botao-denunciar').forEach((b) => {
        b.addEventListener('click', () => denunciarAvaliacao(Number(b.dataset.id), b));
    });
    const botaoOrcamentoEl = document.getElementById('botao-orcamento');
    if (botaoOrcamentoEl) {
        botaoOrcamentoEl.addEventListener('click', () => abrirFormularioOrcamento(oficina));
    }
    const botaoAgendarEl = document.getElementById('botao-agendar');
    if (botaoAgendarEl) {
        botaoAgendarEl.addEventListener('click', () => abrirFormularioAgendamento(oficina));
    }
    const botaoAvaliarEl = document.getElementById('botao-avaliar');
    if (botaoAvaliarEl) {
        botaoAvaliarEl.addEventListener('click', () => {
            document.getElementById('input-comentario')?.focus();
            document.getElementById('bloco-novo-comentario')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
    // Se chegamos aqui por redirecionamento pós-login com intenção de avaliar,
    // já leva o usuário direto até a caixa de comentário.
    const params2 = new URLSearchParams(window.location.search);
    if (usuario && params2.get('acao') === 'avaliar') {
        setTimeout(() => {
            document.getElementById('bloco-novo-comentario')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            document.getElementById('input-comentario')?.focus();
        }, 100);
    }
}

const ORC_MAX_ARQUIVOS = 5;
const ORC_MAX_IMAGEM = 5 * 1024 * 1024;
const ORC_MAX_VIDEO = 25 * 1024 * 1024;
async function abrirFormularioOrcamento(oficina) {
    const bloco = document.getElementById('bloco-orcamento');
    bloco.innerHTML = '<p>Carregando…</p>';
    let veiculos = [];
    let todasOficinas = [];
    try {
        const [rv, ro] = await Promise.all([
            chamarApi('/veiculos/list_veiculos.php'),
            chamarApi('/oficinas/list_oficinas.php'),
        ]);
        veiculos = rv.veiculos;
        todasOficinas = ro.oficinas.filter((o) => o.id_oficina !== oficina.id_oficina);
    } catch (erro) {
        bloco.innerHTML = `<p class="mensagem-erro mensagem-erro--visivel">${escapeHtml(erro.message)}</p>`;
        return;
    }
    if (veiculos.length === 0) {
        bloco.innerHTML = '<p class="mensagem-erro mensagem-erro--visivel">Cadastre um veículo no seu perfil antes de pedir um orçamento.</p>';
        return;
    }
    bloco.innerHTML = `
        <form id="form-orcamento" class="cartao-formulario">
            <h3>Solicitar orçamento</h3>
            <p style="color: var(--texto-secundario); font-size:0.85rem; margin-top:-8px;">
                Descreva o problema e, se quiser, anexe fotos ou vídeo. Você pode enviar o mesmo pedido a até 2 outras oficinas para comparar.
            </p>
            <p id="erro-orcamento" class="mensagem-erro"></p>
            <p id="sucesso-orcamento" class="mensagem-erro" style="background-color:#EAFBF1; color: var(--verde-sucesso);"></p>
            <div class="campo-formulario">
                <label for="orc-veiculo">Veículo</label>
                <select id="orc-veiculo" required>${veiculos.map((v) => `<option value="${v.id_veiculo}">${escapeHtml(v.placa)}</option>`).join('')}</select>
            </div>
            <div class="campo-formulario">
                <label for="orc-servico">Serviço</label>
                <select id="orc-servico">
                    <option value="">Não sei / outro problema</option>
                    ${oficina.servicos.map((s) => `<option value="${s.id_servico}">${escapeHtml(s.nome)}</option>`).join('')}
                </select>
            </div>
            <div class="campo-formulario">
                <label for="orc-descricao">Descreva o problema</label>
                <textarea id="orc-descricao" rows="4" minlength="10" maxlength="2000" required
                    style="width:100%; padding:10px 12px; border:1px solid var(--borda); border-radius: var(--raio-botao); font-family: var(--fonte-corpo);"
                    placeholder="Ex: o carro vibra ao frear acima de 80 km/h e há um barulho na roda dianteira"></textarea>
            </div>
            <div class="campo-formulario">
                <label for="orc-arquivos">Fotos ou vídeo (opcional — até ${ORC_MAX_ARQUIVOS}; imagem até 5 MB, vídeo até 25 MB)</label>
                <input type="file" id="orc-arquivos" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm">
            </div>
            <div class="campo-formulario">
                <label>Enviar também para (opcional, até 2)</label>
                <div id="orc-outras" style="max-height:160px; overflow-y:auto; border:1px solid var(--borda); border-radius: var(--raio-botao); padding:6px 12px;"></div>
            </div>
            <div style="display:flex; gap:10px;">
                <button type="submit" class="botao botao--primario">Enviar pedido</button>
                <button type="button" id="orc-cancelar" class="botao botao--secundario">Cancelar</button>
            </div>
        </form>`;

    const selServico = document.getElementById('orc-servico');
    const caixaOutras = document.getElementById('orc-outras');
    function desenharOutras() {
        const idServico = selServico.value ? Number(selServico.value) : null;
        const candidatas = todasOficinas.filter((o) => idServico === null || o.servicos.some((s) => s.id_servico === idServico));
        caixaOutras.innerHTML = candidatas.length
            ? candidatas.map((o) => `
                <label style="display:flex; gap:8px; align-items:center; margin:6px 0;">
                    <input type="checkbox" class="orc-outra" value="${o.id_oficina}"> ${escapeHtml(o.nome)}${o.verificada ? ' <span style="color: var(--azul-mecon);">✔</span>' : ''}
                </label>`).join('')
            : '<p style="color: var(--texto-secundario); font-size:0.85rem;">Nenhuma outra oficina oferece este serviço.</p>';
        caixaOutras.querySelectorAll('.orc-outra').forEach((c) => c.addEventListener('change', () => {
            if (caixaOutras.querySelectorAll('.orc-outra:checked').length > 2) {
                c.checked = false;
                alert('Você pode escolher até 2 outras oficinas (3 no total).');
            }
        }));
    }
    selServico.addEventListener('change', desenharOutras);
    desenharOutras();
    document.getElementById('orc-cancelar').addEventListener('click', () => { bloco.innerHTML = ''; });

    document.getElementById('form-orcamento').addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const erroEl = document.getElementById('erro-orcamento');
        const sucessoEl = document.getElementById('sucesso-orcamento');
        erroEl.classList.remove('mensagem-erro--visivel');
        const arquivos = Array.from(document.getElementById('orc-arquivos').files);
        if (arquivos.length > ORC_MAX_ARQUIVOS) {
            erroEl.textContent = `Envie no máximo ${ORC_MAX_ARQUIVOS} arquivos.`;
            erroEl.classList.add('mensagem-erro--visivel');
            return;
        }
        for (const a of arquivos) {
            const limite = a.type.startsWith('video/') ? ORC_MAX_VIDEO : ORC_MAX_IMAGEM;
            if (a.size > limite) {
                erroEl.textContent = `"${a.name}" é maior que ${limite / 1024 / 1024} MB.`;
                erroEl.classList.add('mensagem-erro--visivel');
                return;
            }
        }
        const dados = new FormData();
        dados.append('id_veiculo', document.getElementById('orc-veiculo').value);
        if (selServico.value) dados.append('id_servico', selServico.value);
        dados.append('descricao', document.getElementById('orc-descricao').value.trim());
        dados.append('ids_oficinas[]', String(oficina.id_oficina));
        caixaOutras.querySelectorAll('.orc-outra:checked').forEach((c) => dados.append('ids_oficinas[]', c.value));
        arquivos.forEach((a) => dados.append('anexos[]', a));
        const botao = evento.submitter;
        if (botao) { botao.disabled = true; botao.textContent = 'Enviando…'; }
        try {
            await chamarApiUpload('/orcamentos/create_pedido.php', dados);
            sucessoEl.innerHTML = 'Pedido enviado! Acompanhe as respostas na aba <a href="perfil.html?aba=orcamentos">Orçamentos</a> do seu perfil.';
            sucessoEl.classList.add('mensagem-erro--visivel');
            document.getElementById('form-orcamento').reset();
            desenharOutras();
        } catch (erro) {
            erroEl.textContent = erro.message;
            erroEl.classList.add('mensagem-erro--visivel');
        } finally {
            if (botao) { botao.disabled = false; botao.textContent = 'Enviar pedido'; }
        }
    });
}

async function abrirFormularioAgendamento(oficina) {
    const bloco = document.getElementById('bloco-agendamento');
    bloco.innerHTML = '<p>Carregando seus veículos…</p>';
    let veiculos = [];
    try {
        const resposta = await chamarApi('/veiculos/list_veiculos.php');
        veiculos = resposta.veiculos;
    } catch (erro) {
        bloco.innerHTML = `<p class="mensagem-erro mensagem-erro--visivel">${escapeHtml(erro.message)}</p>`;
        return;
    }
    if (veiculos.length === 0) {
        bloco.innerHTML = `
            <p class="mensagem-erro mensagem-erro--visivel">
                Cadastre um veículo no seu perfil antes de solicitar um agendamento.
            </p>
        `;
        return;
    }
    const opcoesServico = oficina.servicos.map((s) => `<option value="${s.id_servico}">${escapeHtml(s.nome)} — R$ ${s.preco.toFixed(2)}</option>`).join('');
    const opcoesVeiculo = veiculos.map((v) => `<option value="${v.id_veiculo}">${escapeHtml(v.placa)}</option>`).join('');
    bloco.innerHTML = `
        <form id="form-agendamento" class="cartao-formulario">
            <h3>Solicitar agendamento</h3>
            <p id="erro-agendamento" class="mensagem-erro"></p>
            <p id="sucesso-agendamento" class="mensagem-erro" style="background-color:#EAFBF1; color: var(--verde-sucesso);"></p>
            <div class="campo-formulario">
                <label for="select-servico-agendamento">Serviço</label>
                <select id="select-servico-agendamento" required>${opcoesServico}</select>
            </div>
            <div class="campo-formulario">
                <label for="select-veiculo-agendamento">Veículo</label>
                <select id="select-veiculo-agendamento" required>${opcoesVeiculo}</select>
            </div>
            <div class="campo-formulario">
                <label for="input-data-agendamento">Data e hora desejadas</label>
                <input type="datetime-local" id="input-data-agendamento" required>
            </div>
            <div class="campo-formulario">
                <label for="input-mensagem-agendamento">Mensagem (opcional)</label>
                <input type="text" id="input-mensagem-agendamento" maxlength="500" placeholder="Ex: prefiro no período da tarde">
            </div>
            <div style="display:flex; gap:10px;">
                <button type="submit" class="botao botao--primario">Enviar solicitação</button>
                <button type="button" id="botao-cancelar-agendamento" class="botao botao--secundario">Cancelar</button>
            </div>
        </form>
    `;
    document.getElementById('botao-cancelar-agendamento').addEventListener('click', () => { bloco.innerHTML = ''; });
    document.getElementById('form-agendamento').addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const dataLocal = document.getElementById('input-data-agendamento').value; // "AAAA-MM-DDTHH:MM"
        const corpo = {
            id_oficina: oficina.id_oficina,
            id_servico: Number(document.getElementById('select-servico-agendamento').value),
            id_veiculo: Number(document.getElementById('select-veiculo-agendamento').value),
            data_hora_solicitada: dataLocal.replace('T', ' ') + ':00',
            mensagem_usuario: document.getElementById('input-mensagem-agendamento').value.trim(),
        };
        const erroEl = document.getElementById('erro-agendamento');
        const sucessoEl = document.getElementById('sucesso-agendamento');
        try {
            await chamarApi('/agendamentos/create_agendamento.php', { method: 'POST', body: JSON.stringify(corpo) });
            sucessoEl.textContent = 'Solicitação enviada! Acompanhe o status na aba "Agendamentos" do seu perfil.';
            sucessoEl.classList.add('mensagem-erro--visivel');
            document.getElementById('form-agendamento').reset();
        } catch (erro) {
            erroEl.textContent = erro.message;
            erroEl.classList.add('mensagem-erro--visivel');
        }
    });
}
async function denunciarAvaliacao(idAvaliacao, botaoEl) {
    const motivo = prompt('Por que você está denunciando esta avaliação? (opcional)');
    if (motivo === null) {
        return; // usuário cancelou
    }
    try {
        await chamarApi('/denuncias/create_denuncia.php', {
            method: 'POST',
            body: JSON.stringify({ id_avaliacao: idAvaliacao, motivo: motivo.trim() }),
        });
        botaoEl.textContent = 'Denúncia enviada — obrigado';
        botaoEl.disabled = true;
        botaoEl.style.cursor = 'default';
        botaoEl.style.textDecoration = 'none';
    } catch (erro) {
        alert(erro.message);
    }
}
function renderizarBlocoComentario(usuario) {
    const bloco = document.getElementById('bloco-novo-comentario');
    if (!usuario) {
        bloco.innerHTML = '';
        return;
    }
    bloco.innerHTML = `
    <h3>Deixe sua avaliação</h3>
    <form id="form-comentario">
      <label class="campo-formulario" style="display:block; max-width: 240px;">
        <span style="display:block; font-weight:600; margin-bottom:6px;">Nota</span>
        <select id="select-nota" required>
          <option value="5">5 - Excelente</option>
          <option value="4">4 - Muito bom</option>
          <option value="3">3 - Bom</option>
          <option value="2">2 - Regular</option>
          <option value="1">1 - Ruim</option>
        </select>
      </label>
      <label class="campo-formulario" style="display:block;">
        <span style="display:block; font-weight:600; margin-bottom:6px;">Comentário</span>
        <textarea id="input-comentario" rows="3" style="width:100%; padding:11px 14px; border:1px solid var(--borda); border-radius: var(--raio-botao); font-family: var(--fonte-corpo);" placeholder="Conte como foi sua experiência"></textarea>
      </label>
      <p id="erro-comentario" class="mensagem-erro"></p>
      <button type="submit" class="botao botao--primario">Avaliar oficina</button>
    </form>
  `;
    document.getElementById('form-comentario').addEventListener('submit', enviarComentario);
}
async function enviarComentario(evento) {
    evento.preventDefault();
    const nota = document.getElementById('select-nota').value;
    const comentario = document.getElementById('input-comentario').value.trim();
    const erroEl = document.getElementById('erro-comentario');
    try {
        await chamarApi('/avaliacoes/create_review.php', {
            method: 'POST',
            body: JSON.stringify({ id_oficina: Number(idOficina), nota: Number(nota), comentario }),
        });
        await carregarTudo();
    } catch (erro) {
        erroEl.textContent = erro.message;
        erroEl.classList.add('mensagem-erro--visivel');
    }
}
async function alternarFavorito() {
    const usuario = obterUsuarioLocal();
    if (!usuario) {
        window.location.href = 'login.html';
        return;
    }
    try {
        if (favoritoAtual) {
            await chamarApi('/favoritos/remove_favorito.php', {
                method: 'POST',
                body: JSON.stringify({ id_oficina: Number(idOficina) }),
            });
        } else {
            await chamarApi('/favoritos/add_favorito.php', {
                method: 'POST',
                body: JSON.stringify({ id_oficina: Number(idOficina) }),
            });
        }
        favoritoAtual = !favoritoAtual;
        const botao = document.getElementById('botao-favoritar');
        botao.textContent = favoritoAtual ? '♥ Remover dos favoritos' : '♡ Favoritar oficina';
    } catch (erro) {
        console.error('Erro ao favoritar:', erro);
    }
}
async function carregarTudo() {
    if (!idOficina) {
        container.innerHTML = '<p>Oficina não especificada.</p>';
        return;
    }
    try {
        const usuario = obterUsuarioLocal();
        const [respostaOficina, respostaAvaliacoes, respostaPromocoes] = await Promise.all([
            chamarApi(`/oficinas/get_oficina.php?id_oficina=${idOficina}`),
            chamarApi(`/avaliacoes/list_reviews.php?id_oficina=${idOficina}`),
            chamarApi(`/promocoes/list_promocoes_ativas.php?id_oficina=${idOficina}`),
        ]);
        if (usuario) {
            try {
                const respostaFavorito = await chamarApi(`/favoritos/check_favorito.php?id_oficina=${idOficina}`);
                favoritoAtual = respostaFavorito.favoritado;
            } catch (erro) {
                favoritoAtual = false;
            }
        }
        renderizarOficina(respostaOficina.oficina, respostaAvaliacoes.avaliacoes, usuario, respostaPromocoes.promocoes);
    } catch (erro) {
        container.innerHTML = `<p>Não foi possível carregar esta oficina: ${escapeHtml(erro.message)}</p>`;
    }
}
carregarTudo();
