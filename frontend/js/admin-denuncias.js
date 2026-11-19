/**
 * MecOn - admin-denuncias.js
 * Fila de moderação: lista denúncias pendentes (mais antigas primeiro),
 * mostra o comentário denunciado e permite remover a avaliação ou
 * descartar a denúncia. Exige login como administrador.
 */
const admin = exigirAdminUI();
const listaContainer = document.getElementById('lista-denuncias-admin');
const checkboxDescartadas = document.getElementById('input-mostrar-descartadas');
let paginaAtualDenuncias = 1;
const paginacaoContainer = document.createElement('div');
paginacaoContainer.id = 'paginacao-denuncias';
listaContainer.insertAdjacentElement('afterend', paginacaoContainer);

function escapeHtml(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}
function estrelas(nota) {
  return '★★★★★☆☆☆☆☆'.slice(5 - nota, 10 - nota);
}

async function carregarDenuncias(pagina = 1) {
  paginaAtualDenuncias = pagina;
  listaContainer.innerHTML = '<p>Carregando fila de moderação…</p>';
  try {
    const status = checkboxDescartadas.checked ? 'todas' : 'pendente';
    const resposta = await chamarApi(`/denuncias/list_denuncias.php?status=${status}&pagina=${pagina}`);
    desenharLista(resposta.denuncias);
    renderizarControlesPaginacao(paginacaoContainer, resposta.paginacao, carregarDenuncias);
  } catch (erro) {
    listaContainer.innerHTML = `<p>Erro ao carregar a fila de moderação: ${escapeHtml(erro.message)}</p>`;
  }
}

function desenharLista(denuncias) {
  if (denuncias.length === 0) {
    listaContainer.innerHTML = '<div class="estado-vazio"><h3>Nenhuma denúncia pendente 🎉</h3></div>';
    return;
  }
  listaContainer.innerHTML = denuncias.map((d) => `
    <div class="avaliacao" data-id="${d.id_denuncia}" style="${d.status === 'descartada' ? 'opacity:0.6;' : ''}">
      <div class="avaliacao__corpo">
        <div class="avaliacao__cabecalho">
          <span class="avaliacao__nome">${escapeHtml(d.nome_autor_avaliacao)} → ${escapeHtml(d.nome_oficina)}</span>
          <span class="avaliacao__estrelas">${estrelas(d.nota)}</span>
        </div>
        <p class="avaliacao__comentario">${escapeHtml(d.comentario || '')}</p>
        <p style="font-size:0.85rem; color: var(--texto-secundario); margin-top:6px;">
          Denunciado por ${escapeHtml(d.nome_denunciante)}${d.motivo ? ' — motivo: "' + escapeHtml(d.motivo) + '"' : ''}
          ${d.status === 'descartada' ? ' · <strong>descartada</strong>' : ''}
        </p>
      </div>
      ${d.status === 'pendente' ? `
        <div class="acoes-moderacao">
          <button type="button" class="botao botao--perigo botao-remover-avaliacao" data-id="${d.id_denuncia}">Remover avaliação</button>
          <button type="button" class="botao botao--secundario botao-descartar-denuncia" data-id="${d.id_denuncia}">Descartar denúncia</button>
        </div>
      ` : ''}
    </div>
  `).join('');
  document.querySelectorAll('.botao-remover-avaliacao').forEach((b) => {
    b.addEventListener('click', () => resolverDenuncia(Number(b.dataset.id), 'remover_avaliacao'));
  });
  document.querySelectorAll('.botao-descartar-denuncia').forEach((b) => {
    b.addEventListener('click', () => resolverDenuncia(Number(b.dataset.id), 'descartar'));
  });
}

async function resolverDenuncia(idDenuncia, acao) {
  const mensagemConfirmacao = acao === 'remover_avaliacao'
    ? 'Remover esta avaliação permanentemente? Isso também resolve qualquer outra denúncia sobre o mesmo comentário.'
    : 'Descartar esta denúncia? O comentário permanece publicado.';
  if (!confirm(mensagemConfirmacao)) return;
  try {
    await chamarApi('/denuncias/resolver_denuncia.php', {
      method: 'POST',
      body: JSON.stringify({ id_denuncia: idDenuncia, acao }),
    });
    carregarDenuncias(paginaAtualDenuncias);
  } catch (erro) {
    alert('Não foi possível concluir a ação: ' + erro.message);
  }
}

checkboxDescartadas.addEventListener('change', () => carregarDenuncias(1));

if (admin) {
  carregarDenuncias();
}
