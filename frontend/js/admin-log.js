/**
 * MecOn - admin-log.js
 * Exige login como administrador.
 */
const admin = exigirAdminUI();
const listaContainer = document.getElementById('lista-log-admin');
const paginacaoContainer = document.createElement('div');
paginacaoContainer.id = 'paginacao-log';
listaContainer.insertAdjacentElement('afterend', paginacaoContainer);

function escapeHtml(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}

const rotulosAcao = {
  excluir: 'Excluiu',
  editar: 'Editou',
  verificar: 'Verificou',
  remover_verificacao: 'Removeu selo de',
  remover_avaliacao_denunciada: 'Removeu (via denúncia)',
  descartar_denuncia: 'Descartou denúncia sobre',
};

function valorExibivel(valor) {
  if (valor === null) return '<em>(vazio)</em>';
  const texto = String(valor);
  return texto.length > 60 ? escapeHtml(texto.slice(0, 60)) + '…' : escapeHtml(texto);
}

function desenharDetalhesCampos(detalhes, idUnico) {
  if (!detalhes || detalhes.length === 0) return '';
  const linhas = detalhes.map((d) => `
    <tr>
      <td style="padding:4px 10px 4px 0; font-weight:600; white-space:nowrap;">${escapeHtml(d.campo)}</td>
      <td style="padding:4px 10px; color:#C0392B;">${valorExibivel(d.valor_anterior)}</td>
      <td style="padding:4px 0; color: var(--verde-sucesso);">${valorExibivel(d.valor_novo)}</td>
    </tr>
  `).join('');
  return `
    <button type="button" class="botao-ver-alteracoes" data-alvo="detalhes-${idUnico}"
      style="background:none; border:none; padding:0; margin-top:4px; color: var(--azul-mecon); font-size:0.8rem; text-decoration:underline; cursor:pointer;">
      Ver alterações (${detalhes.length})
    </button>
    <table id="detalhes-${idUnico}" style="display:none; margin-top:6px; font-size:0.82rem; border-collapse:collapse;">
      <thead>
        <tr style="color: var(--texto-secundario);">
          <th style="text-align:left; padding:0 10px 4px 0;">Campo</th>
          <th style="text-align:left; padding:0 10px 4px 0;">Valor anterior</th>
          <th style="text-align:left; padding:0;">Valor novo</th>
        </tr>
      </thead>
      <tbody>${linhas}</tbody>
    </table>
  `;
}

async function carregarLog(pagina = 1) {
  listaContainer.innerHTML = '<p>Carregando log…</p>';
  try {
    const resposta = await chamarApi(`/admin/list_log_auditoria.php?pagina=${pagina}`);
    desenharLista(resposta.logs);
    renderizarControlesPaginacao(paginacaoContainer, resposta.paginacao, carregarLog);
  } catch (erro) {
    listaContainer.innerHTML = `<p>Erro ao carregar o log: ${escapeHtml(erro.message)}</p>`;
  }
}

function desenharLista(logs) {
  if (logs.length === 0) {
    listaContainer.innerHTML = '<div class="estado-vazio"><h3>Nenhum registro ainda</h3></div>';
    return;
  }
  listaContainer.innerHTML = logs.map((l) => {
    const dataFormatada = new Date(l.criado_em.replace(' ', 'T')).toLocaleString('pt-BR', {
      day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });
    const rotuloAcao = rotulosAcao[l.acao] || l.acao;
    const ator = l.nome_ator ? escapeHtml(l.nome_ator) : `admin removido (id ${l.id_ator})`;
    return `
      <div class="avaliacao">
        <div class="avaliacao__corpo">
          <span class="avaliacao__nome">${ator} · ${rotuloAcao} ${escapeHtml(l.entidade)}${l.id_entidade ? ' #' + l.id_entidade : ''}</span>
          <p class="avaliacao__comentario">
            ${dataFormatada}${l.detalhes ? ' — ' + escapeHtml(l.detalhes) : ''}
          </p>
          ${desenharDetalhesCampos(l.detalhes_campos, l.id_log)}
        </div>
      </div>
    `;
  }).join('');
  document.querySelectorAll('.botao-ver-alteracoes').forEach((b) => {
    b.addEventListener('click', () => {
      const tabela = document.getElementById(b.dataset.alvo);
      const aberta = tabela.style.display !== 'none';
      tabela.style.display = aberta ? 'none' : 'table';
      b.textContent = b.textContent.replace(aberta ? 'Ocultar' : 'Ver', aberta ? 'Ver' : 'Ocultar');
    });
  });
}

if (admin) {
  carregarLog();
}
