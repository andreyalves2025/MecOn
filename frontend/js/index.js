/**
 * MecOn - index.js
 * Lógica da página inicial: redireciona para a página de oficinas
 * filtradas com o termo de busca informado.
 */
document.getElementById('form-busca-inicial').addEventListener('submit', (evento) => {
    evento.preventDefault();
    const termo = document.getElementById('input-localizacao').value.trim();
    const parametros = new URLSearchParams();
    if (termo !== '') {
        parametros.set('q', termo);
    }
    window.location.href = `pages/oficinas.html?${parametros.toString()}`;
});

function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
}
function formatarDataBR(dataIso) {
    const [ano, mes, dia] = dataIso.split('-');
    return `${dia}/${mes}/${ano}`;
}
async function carregarOfertasDestaque() {
    try {
        const resposta = await chamarApi('/promocoes/list_promocoes_ativas.php');
        if (resposta.promocoes.length === 0) return;
        document.getElementById('secao-ofertas').style.display = 'block';
        document.getElementById('lista-ofertas').innerHTML = resposta.promocoes.map((p) => `
            <a href="pages/oficina.html?id=${p.id_oficina}"
                style="display:block; padding:12px 16px; background:#FFF4E5; border-left:3px solid #E8A33D; border-radius: var(--raio-botao); text-decoration:none; color:inherit;">
                <p style="font-weight:700; margin:0 0 2px;">🏷️ ${escapeHtml(p.titulo)} — ${escapeHtml(p.nome_oficina)}</p>
                ${p.descricao ? `<p style="margin:0 0 2px; font-size:0.9rem;">${escapeHtml(p.descricao)}</p>` : ''}
                <p style="margin:0; font-size:0.8rem; color: var(--texto-secundario);">Válida até ${formatarDataBR(p.data_fim)}</p>
            </a>
        `).join('');
    } catch (erro) {
        console.error('Erro ao carregar ofertas em destaque:', erro);
    }
}
carregarOfertasDestaque();

async function carregarManutencoesPendentes() {
    const lembretes = await obterLembretesPendentes();
    if (lembretes.length === 0) return;
    document.getElementById('secao-lembretes').style.display = 'block';
    document.getElementById('lista-lembretes').innerHTML = lembretes.map((l) => {
        const cor = l.status === 'atrasado' ? '#C0392B' : '#E8A33D';
        return `
            <div style="padding:12px 16px; background:#FFF4E5; border-left:3px solid ${cor}; border-radius: var(--raio-botao); display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <span><span style="color:${cor};" aria-hidden="true">⚠</span> ${descreverLembrete(l)}</span>
                <a href="pages/oficinas.html?servico=${l.id_servico}" class="botao botao--secundario" style="padding:6px 14px; font-size:0.85rem;">Encontrar oficinas</a>
            </div>
        `;
    }).join('');
}
carregarManutencoesPendentes();

async function carregarOfertasPersonalizadas() {
    const usuario = obterUsuarioLocal();
    if (!usuario || usuario.tipo !== 'cliente') return;
    try {
        const resposta = await chamarApi('/promocoes/ofertas_personalizadas.php');
        if (resposta.ofertas.length === 0) return;
        document.getElementById('secao-ofertas-pessoais').style.display = 'block';
        document.getElementById('lista-ofertas-pessoais').innerHTML = resposta.ofertas.map((o) => `
            <a href="pages/oficina.html?id=${o.id_oficina}"
                style="display:block; padding:12px 16px; background:#EEF6FF; border-left:3px solid var(--azul-mecon); border-radius: var(--raio-botao); text-decoration:none; color:inherit;">
                <p style="font-weight:700; margin:0 0 2px;">🏷️ ${escapeHtml(o.titulo)} — ${escapeHtml(o.nome_oficina)}${o.oficina_verificada ? ' <span style="color: var(--azul-mecon);">✔</span>' : ''}</p>
                ${o.descricao ? `<p style="margin:0 0 4px; font-size:0.9rem;">${escapeHtml(o.descricao)}</p>` : ''}
                <p style="margin:0 0 6px; font-size:0.8rem; color: var(--texto-secundario);">Válida até ${formatarDataBR(o.data_fim)}${o.nome_servico ? ' · ' + escapeHtml(o.nome_servico) : ''}</p>
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                    ${o.motivos.map((m) => `<span class="tag-servico">${escapeHtml(m.texto)}</span>`).join('')}
                </div>
            </a>
        `).join('');
    } catch (erro) {
        console.error('Erro ao carregar ofertas personalizadas:', erro);
    }
}
carregarOfertasPersonalizadas();
