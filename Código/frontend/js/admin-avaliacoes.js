/**
 * MecOn - admin-avaliacoes.js
 * Lista todas as avaliações da plataforma, com busca por nome de usuário
 * ou de oficina (server-side, compatível com paginação), e permite apenas
 * exclusão (moderação de conteúdo). Exige login como administrador.
 */
const admin = exigirAdminUI();
const listaContainer = document.getElementById('lista-avaliacoes-admin');
const inputBusca = document.getElementById('input-busca-avaliacao');
let timeoutBusca = null;
let paginaAtualAvaliacoes = 1;
function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
}
function estrelas(nota) {
    return '★★★★★☆☆☆☆☆'.slice(5 - nota, 10 - nota);
}
async function carregarAvaliacoes(termo = '', pagina = 1) {
    paginaAtualAvaliacoes = pagina;
    listaContainer.innerHTML = '<p>Carregando avaliações…</p>';
    document.getElementById('paginacao-avaliacoes')?.remove();
    try {
        const parametros = new URLSearchParams({ pagina: String(pagina) });
        if (termo) parametros.set('busca', termo);
        const resposta = await chamarApi(`/avaliacoes/list_all_reviews.php?${parametros.toString()}`);
        desenharLista(resposta.avaliacoes);
        const paginacaoEl = document.createElement('div');
        paginacaoEl.id = 'paginacao-avaliacoes';
        listaContainer.insertAdjacentElement('afterend', paginacaoEl);
        renderizarControlesPaginacao(paginacaoEl, resposta.paginacao, (novaPagina) => carregarAvaliacoes(termo, novaPagina));
    } catch (erro) {
        listaContainer.innerHTML = `<p>Erro ao carregar avaliações: ${escapeHtml(erro.message)}</p>`;
    }
}
function desenharLista(avaliacoes) {
    if (avaliacoes.length === 0) {
        listaContainer.innerHTML = '<div class="estado-vazio"><h3>Nenhuma avaliação encontrada</h3></div>';
        return;
    }
    listaContainer.innerHTML = avaliacoes.map((a) => `
    <div class="avaliacao">
      <div class="avaliacao__corpo">
        <div class="avaliacao__cabecalho">
          <span class="avaliacao__nome">${escapeHtml(a.nome_usuario)} → ${escapeHtml(a.nome_oficina)}</span>
          <span class="avaliacao__estrelas">${estrelas(a.nota)}</span>
        </div>
        <p class="avaliacao__comentario">${escapeHtml(a.comentario || '')}</p>
      </div>
      <button type="button" class="botao botao--perigo botao-excluir-avaliacao" data-id="${a.id_avaliacao}">Excluir</button>
    </div>
  `).join('');
    document.querySelectorAll('.botao-excluir-avaliacao').forEach((b) => {
        b.addEventListener('click', () => excluirAvaliacao(Number(b.dataset.id)));
    });
}
inputBusca.addEventListener('input', () => {
    clearTimeout(timeoutBusca);
    timeoutBusca = setTimeout(() => carregarAvaliacoes(inputBusca.value.trim(), 1), 300);
});
async function excluirAvaliacao(idAvaliacao) {
    if (!confirm('Excluir esta avaliação permanentemente?')) return;
    try {
        await chamarApi('/avaliacoes/delete_review.php', {
            method: 'POST',
            body: JSON.stringify({ id_avaliacao: idAvaliacao }),
        });
        carregarAvaliacoes(inputBusca.value.trim(), paginaAtualAvaliacoes);
    } catch (erro) {
        alert('Não foi possível excluir: ' + erro.message);
    }
}
if (admin) {
    carregarAvaliacoes();
}
