/**
 * MecOn - ordem-servico.js
 * Visualização/impressão da ordem de serviço de um agendamento.
 * URL: ordem-servico.html?como=usuario|oficina&id_agendamento=ID
 * O cliente só lê; a oficina edita pela aba Agendamentos do perfil.
 */
const parametros = new URLSearchParams(window.location.search);
const como = parametros.get('como') === 'oficina' ? 'oficina' : 'usuario';
const idAgendamento = parametros.get('id_agendamento');
const container = document.getElementById('conteudo-os');

document.getElementById('link-voltar').href = como === 'oficina' ? 'perfil-oficina.html' : 'perfil.html?aba=agendamentos';
document.getElementById('botao-imprimir').addEventListener('click', () => window.print());

const ROTULOS_ETAPA = {
  aberta: 'Aberta',
  em_execucao: 'Em execução',
  aguardando_peca: 'Aguardando peça',
  pronta: 'Pronta para retirada',
  entregue: 'Concluída',
};

function linhaInfo(rotulo, valor) {
  return valor ? `<div><span style="color: var(--texto-secundario);">${rotulo}:</span> ${_escHtml(valor)}</div>` : '';
}

async function carregar() {
  if (!idAgendamento) {
    container.innerHTML = '<p>Agendamento não informado.</p>';
    return;
  }
  try {
    const { agendamento: ag, os } = await chamarApi(`/ordens_servico/get_os.php?como=${como}&id_agendamento=${encodeURIComponent(idAgendamento)}`);
    if (!os) {
      container.innerHTML = `<div class="estado-vazio"><h3>Ainda não há ordem de serviço</h3><p>${como === 'usuario' ? 'A oficina abre a ordem de serviço quando começa a atender o seu veículo.' : 'Abra a ordem de serviço pela aba Agendamentos.'}</p></div>`;
      return;
    }
    const t = os.totais;
    const dataAg = new Date(ag.data_hora_solicitada.replace(' ', 'T')).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    const linhasItens = os.itens.map((i) => {
      const sub = i.valor_unitario !== null ? i.quantidade * i.valor_unitario : null;
      return `<tr>
        <td>${_escHtml(i.nome)}</td>
        <td class="num">${i.quantidade.toLocaleString('pt-BR')}</td>
        <td class="num">${i.valor_unitario !== null ? formatarReaisGeral(i.valor_unitario) : '—'}</td>
        <td class="num">${sub !== null ? formatarReaisGeral(sub) : '—'}</td>
      </tr>`;
    }).join('');
    container.innerHTML = `
      <div class="os-bloco" style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
        <div>
          <h1 style="margin:0;">Ordem de serviço nº ${os.id_os}</h1>
          <div style="color: var(--texto-secundario); font-size:0.9rem;">${_escHtml(ag.nome_oficina)} · ${_escHtml(ag.endereco)}, ${_escHtml(ag.bairro)} — ${_escHtml(ag.cidade)} · ${_escHtml(ag.telefone_oficina)}</div>
        </div>
        <span style="border:1px solid var(--azul-mecon); color: var(--azul-mecon); border-radius:999px; padding:4px 14px; font-weight:600;">${ROTULOS_ETAPA[os.etapa]}</span>
      </div>
      <div class="os-bloco os-grade">
        ${linhaInfo('Cliente', ag.nome_cliente)}
        ${linhaInfo('Veículo', ag.placa)}
        ${linhaInfo('Serviço', ag.nome_servico)}
        ${linhaInfo('Data agendada', dataAg)}
        ${linhaInfo('Responsável', os.responsavel)}
        ${linhaInfo('Km na entrada', os.km_entrada !== null ? os.km_entrada.toLocaleString('pt-BR') + ' km' : '')}
      </div>
      ${os.diagnostico ? `<div class="os-bloco"><h3>Diagnóstico</h3><div style="white-space:pre-wrap;">${_escHtml(os.diagnostico)}</div></div>` : ''}
      <div class="os-bloco">
        <h3>Peças e serviços</h3>
        ${os.itens.length ? `<table class="os-tabela"><thead><tr><th>Peça</th><th class="num">Qtd</th><th class="num">Unitário</th><th class="num">Subtotal</th></tr></thead><tbody>${linhasItens}</tbody></table>` : '<p style="color: var(--texto-secundario);">Nenhuma peça lançada.</p>'}
        <table class="os-tabela" style="max-width:360px; margin-left:auto;">
          <tbody>
            <tr><td>Peças</td><td class="num">${t.valor_pecas !== null ? formatarReaisGeral(t.valor_pecas) : '—'}</td></tr>
            <tr><td>Mão de obra</td><td class="num">${t.valor_mao_obra !== null ? formatarReaisGeral(t.valor_mao_obra) : '—'}</td></tr>
            ${t.desconto !== null ? `<tr><td>Desconto</td><td class="num">− ${formatarReaisGeral(t.desconto)}</td></tr>` : ''}
            <tr><td><strong>Total</strong></td><td class="num"><strong>${formatarReaisGeral(t.valor_total)}</strong></td></tr>
          </tbody>
        </table>
        ${ag.valor_orcamento !== null ? `<p style="font-size:0.82rem; color: var(--texto-secundario);">Orçamento aprovado anteriormente: ${formatarReaisGeral(ag.valor_orcamento)}</p>` : ''}
      </div>
      ${os.observacoes ? `<div class="os-bloco"><h3>Observações</h3><div style="white-space:pre-wrap;">${_escHtml(os.observacoes)}</div></div>` : ''}
      <p style="font-size:0.8rem; color: var(--texto-secundario);">Aberta em ${formatarDataISO(os.criado_em)}${os.concluida_em ? ' · concluída em ' + formatarDataISO(os.concluida_em) : ''} · Documento gerado pelo MecOn.</p>`;
  } catch (erro) {
    container.innerHTML = `<p>Não foi possível carregar a ordem de serviço: ${_escHtml(erro.message)}</p>`;
  }
}
carregar();
