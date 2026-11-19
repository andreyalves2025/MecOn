/**
 * MecOn - admin-usuarios.js
 * Busca usuários por nome/email, exibe detalhe (incluindo avaliações feitas)
 * e permite exclusão. Exige login como administrador.
 */
const admin = exigirAdminUI();
const listaContainer = document.getElementById('lista-usuarios-admin');
const detalheContainer = document.getElementById('detalhe-usuario-container');
const inputBusca = document.getElementById('input-busca-usuario');
let timeoutBusca = null;
let paginaAtualUsuarios = 1;
function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
}
function estrelas(nota) {
    return '★★★★★☆☆☆☆☆'.slice(5 - nota, 10 - nota);
}
async function carregarUsuarios(termo = '', pagina = 1) {
    paginaAtualUsuarios = pagina;
    listaContainer.innerHTML = '<p>Carregando usuários…</p>';
    document.getElementById('paginacao-usuarios')?.remove();
    try {
        const parametros = new URLSearchParams({ pagina: String(pagina) });
        if (termo) parametros.set('busca', termo);
        const resposta = await chamarApi(`/usuarios/list_usuarios.php?${parametros.toString()}`);
        desenharLista(resposta.usuarios);
        const paginacaoEl = document.createElement('div');
        paginacaoEl.id = 'paginacao-usuarios';
        listaContainer.insertAdjacentElement('afterend', paginacaoEl);
        renderizarControlesPaginacao(paginacaoEl, resposta.paginacao, (novaPagina) => carregarUsuarios(termo, novaPagina));
    } catch (erro) {
        listaContainer.innerHTML = `<p>Erro ao carregar usuários: ${escapeHtml(erro.message)}</p>`;
    }
}
function desenharLista(usuarios) {
    if (usuarios.length === 0) {
        listaContainer.innerHTML = '<div class="estado-vazio"><h3>Nenhum usuário encontrado</h3></div>';
        return;
    }
    listaContainer.innerHTML = usuarios.map((u) => {
        const ehAdmin = u.tipo === 'admin';
        const estiloCard = ehAdmin
            ? 'background-color: var(--azul-mecon); color: var(--branco);'
            : '';
        const estiloEmail = ehAdmin
            ? 'font-size:0.85rem; color: rgba(255,255,255,0.85); margin: 4px 0 0;'
            : 'font-size:0.85rem; color: var(--texto-secundario); margin: 4px 0 0;';
        const classeBotao = ehAdmin ? 'botao--secundario-invertido' : 'botao--secundario';
        return `
      <div class="avaliacao" data-usuario-card="${u.id_usuario}" style="${estiloCard}">
        <div class="avaliacao__corpo">
          <span class="avaliacao__nome">${escapeHtml(u.nome)}</span>
          <p style="${estiloEmail}">${escapeHtml(u.email)}</p>
        </div>
        <button type="button" class="botao ${classeBotao} botao-ver-usuario" data-id="${u.id_usuario}">Ver detalhes</button>
      </div>
    `;
    }).join('');
    document.querySelectorAll('.botao-ver-usuario').forEach((b) => {
        b.addEventListener('click', () => abrirDetalhe(Number(b.dataset.id), b.closest('.avaliacao')));
    });
}
inputBusca.addEventListener('input', () => {
    clearTimeout(timeoutBusca);
    timeoutBusca = setTimeout(() => carregarUsuarios(inputBusca.value.trim(), 1), 300);
});
async function abrirDetalhe(idUsuario, cardElement) {
    // Remove qualquer detalhe já aberto antes de abrir um novo
    document.querySelectorAll('.detalhe-usuario-inline').forEach((el) => el.remove());
    detalheContainer.innerHTML = '';
    const placeholder = document.createElement('div');
    placeholder.className = 'detalhe-usuario-inline';
    placeholder.innerHTML = '<p style="margin-top:16px;">Carregando detalhes…</p>';
    cardElement.insertAdjacentElement('afterend', placeholder);
    try {
        const resposta = await chamarApi(`/usuarios/get_usuario.php?id_usuario=${idUsuario}`);
        const usuario = resposta.usuario;
        const avaliacoesHtml = usuario.avaliacoes.length
            ? usuario.avaliacoes.map((a) => `
          <div class="avaliacao" data-id-avaliacao="${a.id_avaliacao}">
            <div class="avaliacao__corpo">
              <div class="avaliacao__cabecalho">
                <span class="avaliacao__nome">${escapeHtml(a.nome_oficina)}</span>
                <span class="avaliacao__estrelas">${estrelas(a.nota)}</span>
              </div>
              <p class="avaliacao__comentario">${escapeHtml(a.comentario || '')}</p>
            </div>
            <button type="button" class="botao botao--perigo botao-excluir-comentario" data-id="${a.id_avaliacao}">Excluir</button>
          </div>
        `).join('')
            : '<p style="color: var(--texto-secundario);">Este usuário ainda não fez avaliações.</p>';
        const ehAdminVisualizado = usuario.tipo === 'admin';
        const secaoAvaliacoesHtml = ehAdminVisualizado
            ? ''
            : `
        <h4 style="margin-top:20px;">Avaliações feitas</h4>
        <div class="lista-avaliacoes">${avaliacoesHtml}</div>
      `;
        const ehVoceMesmo = admin && Number(admin.id_usuario) === Number(usuario.id_usuario);
        const acoesHtml = ehVoceMesmo
            ? `
        <p style="color: var(--texto-secundario); font-size:0.85rem; margin-top:20px;">
          Este é o seu próprio usuário administrador — não é possível excluí-lo.
        </p>
        <button type="button" class="botao botao--secundario botao-fechar-detalhe" style="margin-top:10px;">Fechar</button>
      `
            : `
        <div style="display:flex; gap:10px; margin-top:20px;">
          <button type="button" class="botao botao--perigo botao-excluir-usuario" data-id="${usuario.id_usuario}">Excluir usuário</button>
          <button type="button" class="botao botao--secundario botao-fechar-detalhe">Fechar</button>
        </div>
      `;
        placeholder.innerHTML = `
      <div class="cartao-formulario" style="margin: 16px 0 20px; max-width: 100%;">
        <h3>${escapeHtml(usuario.nome)}</h3>
        <p style="color: var(--texto-secundario);">${escapeHtml(usuario.email)} · Tipo: ${escapeHtml(usuario.tipo)}</p>
        <p style="color: var(--texto-secundario);">Cadastrado em: ${escapeHtml(usuario.data_cadastro)}</p>
        ${secaoAvaliacoesHtml}
        ${acoesHtml}
      </div>
    `;
        placeholder.querySelector('.botao-fechar-detalhe').addEventListener('click', () => {
            placeholder.remove();
        });
        const botaoExcluir = placeholder.querySelector('.botao-excluir-usuario');
        if (botaoExcluir) {
            botaoExcluir.addEventListener('click', () => excluirUsuario(idUsuario));
        }
        placeholder.querySelectorAll('.botao-excluir-comentario').forEach((b) => {
            b.addEventListener('click', () => excluirComentario(Number(b.dataset.id), idUsuario, cardElement));
        });
    } catch (erro) {
        placeholder.innerHTML = `<p>Erro ao carregar detalhes: ${escapeHtml(erro.message)}</p>`;
    }
}
async function excluirComentario(idAvaliacao, idUsuario, cardElement) {
    if (!confirm('Excluir este comentário permanentemente?')) return;
    try {
        await chamarApi('/avaliacoes/delete_review.php', {
            method: 'POST',
            body: JSON.stringify({ id_avaliacao: idAvaliacao }),
        });
        abrirDetalhe(idUsuario, cardElement);
    } catch (erro) {
        alert('Não foi possível excluir o comentário: ' + erro.message);
    }
}
async function excluirUsuario(idUsuario) {
    if (!confirm('Excluir este usuário? Favoritos, avaliações e histórico vinculados também serão excluídos permanentemente.')) {
        return;
    }
    try {
        await chamarApi('/usuarios/delete_usuario.php', {
            method: 'POST',
            body: JSON.stringify({ id_usuario: idUsuario }),
        });
        document.querySelectorAll('.detalhe-usuario-inline').forEach((el) => el.remove());
        carregarUsuarios(inputBusca.value.trim(), paginaAtualUsuarios);
    } catch (erro) {
        alert('Não foi possível excluir: ' + erro.message);
    }
}
if (admin) {
    carregarUsuarios();
}
