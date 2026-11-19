/**
 * MecOn - admin-dashboard.js
 * Exige login como administrador.
 */
const admin = exigirAdminUI();
const container = document.getElementById('conteudo-dashboard');

function escapeHtml(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}

async function carregarDashboard() {
  try {
    const resposta = await chamarApi('/admin/dashboard_metricas.php');
    desenharDashboard(resposta.totais, resposta.top_oficinas, resposta.distribuicao_notas);
  } catch (erro) {
    container.innerHTML = `<p>Não foi possível carregar as métricas: ${escapeHtml(erro.message)}</p>`;
  }
}

function desenharDashboard(totais, topOficinas, distribuicaoNotas) {
  const cartoes = [
    ['Usuários', totais.total_usuarios],
    ['Oficinas', totais.total_oficinas],
    ['Oficinas verificadas', totais.total_oficinas_verificadas],
    ['Avaliações', totais.total_avaliacoes],
    ['Nota média geral', totais.media_geral_avaliacoes != null ? Number(totais.media_geral_avaliacoes).toFixed(1).replace('.', ',') : '—'],
    ['Denúncias pendentes', totais.denuncias_pendentes],
    ['Agendamentos pendentes', totais.agendamentos_pendentes],
    ['Novos usuários (30d)', totais.novos_usuarios_30_dias],
    ['Novas oficinas (30d)', totais.novas_oficinas_30_dias],
  ];
  const gradeCartoes = cartoes.map(([rotulo, valor]) => `
    <div class="cartao-metrica">
      <strong>${valor}</strong>
      <span>${rotulo}</span>
    </div>
  `).join('');

  const linhasTop = topOficinas.map((o, i) => `
    <tr>
      <td>${i + 1}º</td>
      <td><a href="oficina.html?id=${o.id_oficina}">${escapeHtml(o.nome)}</a></td>
      <td>${o.total_avaliacoes}</td>
      <td>${Number(o.media).toFixed(1).replace('.', ',')} ★</td>
    </tr>
  `).join('');

  const totalNotas = distribuicaoNotas.reduce((soma, d) => soma + d.total, 0) || 1;
  const notasCompletas = [5, 4, 3, 2, 1].map((n) => distribuicaoNotas.find((d) => Number(d.nota) === n) || { nota: n, total: 0 });
  const linhasDistribuicao = notasCompletas.map((d) => `
    <div style="display:flex; align-items:center; gap:10px; margin:6px 0;">
      <span style="width:36px;">${d.nota} ★</span>
      <div style="flex:1; background:#eee; border-radius:6px; overflow:hidden; height:14px;">
        <div style="width:${(d.total / totalNotas) * 100}%; background: var(--azul-mecon); height:100%;"></div>
      </div>
      <span style="width:40px; text-align:right; color: var(--texto-secundario); font-size:0.85rem;">${d.total}</span>
    </div>
  `).join('');

  container.innerHTML = `
    <div class="grade-metricas">${gradeCartoes}</div>
    <h2>Top 5 oficinas por volume de avaliações</h2>
    <table class="tabela-comparar" style="width:100%; border-collapse:collapse;">
      <thead>
        <tr><th>#</th><th>Oficina</th><th>Avaliações</th><th>Nota média</th></tr>
      </thead>
      <tbody>${linhasTop || '<tr><td colspan="4">Sem dados suficientes ainda.</td></tr>'}</tbody>
    </table>
    <h2 style="margin-top:24px;">Distribuição geral de notas</h2>
    ${linhasDistribuicao || '<p>Sem avaliações ainda.</p>'}
  `;
}

if (admin) {
  carregarDashboard();
}
