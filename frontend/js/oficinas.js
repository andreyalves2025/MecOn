/**
 * MecOn - oficinas.js
 * Página de oficinas filtradas: lê parâmetros da URL, carrega serviços
 * para o dropdown, busca oficinas (com busca textual + filtros combinados)
 * e renderiza os cards de resultado.
 */
const grade = document.getElementById('grade-oficinas');
const estadoVazio = document.getElementById('estado-vazio');
const inputBusca = document.getElementById('input-busca');
const selectServico = document.getElementById('select-servico');
const selectAvaliacao = document.getElementById('select-avaliacao');
const inputPrecoMin = document.getElementById('input-preco-min');
const inputPrecoMax = document.getElementById('input-preco-max');
const checkboxVerificadas = document.getElementById('input-somente-verificadas');
const botaoLocalizacao = document.getElementById('botao-usar-localizacao');
let localizacaoUsuario = null; // { lat, lng } quando o usuário autoriza a geolocalização
let paginaAtualOficinas = 1;
const oficinasParaComparar = new Set();
const MAX_COMPARACAO = 3;
const barraComparar = document.createElement('div');
barraComparar.id = 'barra-comparar';
barraComparar.style.cssText = 'position:sticky; bottom:16px; z-index:5; display:none; justify-content:center; margin-top:16px;';
const blocoRecomendadas = document.createElement('div');
blocoRecomendadas.id = 'bloco-recomendadas';
blocoRecomendadas.style.display = 'none';
grade.insertAdjacentElement('beforebegin', blocoRecomendadas);
grade.insertAdjacentElement('beforebegin', barraComparar);
const paginacaoContainer = document.createElement('div');
paginacaoContainer.id = 'paginacao-oficinas';
grade.insertAdjacentElement('afterend', paginacaoContainer);

function atualizarBarraComparar() {
    if (oficinasParaComparar.size < 2) {
        barraComparar.style.display = 'none';
        return;
    }
    barraComparar.style.display = 'flex';
    barraComparar.innerHTML = `
        <div style="background: var(--azul-mecon); color:#fff; padding:10px 18px; border-radius:999px; display:flex; align-items:center; gap:14px; box-shadow:0 2px 10px rgba(0,0,0,0.2);">
            <span>${oficinasParaComparar.size} oficina(s) selecionada(s)</span>
            <button type="button" id="botao-comparar-selecionadas" class="botao" style="background:#fff; color: var(--azul-mecon); padding:6px 16px;">Comparar</button>
        </div>
    `;
    document.getElementById('botao-comparar-selecionadas').addEventListener('click', () => {
        const ids = Array.from(oficinasParaComparar).join(',');
        window.location.href = `comparar.html?ids=${ids}`;
    });
}
function ligarEventosComparar() {
    document.querySelectorAll('.checkbox-comparar').forEach((checkbox) => {
        checkbox.addEventListener('click', (evento) => evento.stopPropagation());
        checkbox.addEventListener('change', () => {
            const id = Number(checkbox.dataset.id);
            if (checkbox.checked) {
                if (oficinasParaComparar.size >= MAX_COMPARACAO) {
                    checkbox.checked = false;
                    alert(`Você pode comparar até ${MAX_COMPARACAO} oficinas por vez.`);
                    return;
                }
                oficinasParaComparar.add(id);
            } else {
                oficinasParaComparar.delete(id);
            }
            atualizarBarraComparar();
        });
    });
}
/**
 * Monta um card de oficina (versão vertical, usada na grade de resultados).
 */
function criarCardOficina(oficina) {
    const nota = oficina.media_avaliacao !== null ? oficina.media_avaliacao.toFixed(1) : '—';
    const seloVerificada = oficina.verificada
        ? '<span title="Oficina verificada pela equipe MecOn" style="color: var(--azul-mecon); margin-left:4px;">✔</span>'
        : '';
    const distancia = oficina.distancia_km !== undefined
        ? `<span style="color: var(--texto-secundario); font-size:0.85rem;"> · ${oficina.distancia_km} km</span>`
        : '';
    const marcado = oficinasParaComparar.has(oficina.id_oficina);
    return `
    <div class="card-oficina card-oficina--vertical" style="position:relative;">
      <label class="chip-comparar">
        <input type="checkbox" class="checkbox-comparar" data-id="${oficina.id_oficina}" ${marcado ? 'checked' : ''}>
        Comparar
      </label>
      <a href="oficina.html?id=${oficina.id_oficina}" style="text-decoration:none; color:inherit;">
        <img src="../img/oficina.jpg" alt="Foto da oficina ${oficina.nome}" class="card-oficina__imagem">
        <div class="card-oficina__info">
          <div class="card-oficina__nome">${escapeHtml(oficina.nome)}${seloVerificada}</div>
          <div class="card-oficina__avaliacao">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9.05 2.93a1 1 0 011.9 0l1.36 2.77 3.06.45a1 1 0 01.55 1.7l-2.21 2.15.52 3.05a1 1 0 01-1.45 1.05L10 12.7l-2.78 1.4a1 1 0 01-1.45-1.05l.52-3.05-2.21-2.15a1 1 0 01.55-1.7l3.06-.45z"/></svg>
            ${nota}${distancia}
          </div>
        </div>
      </a>
    </div>
  `;
}
/**
 * Previne XSS simples ao inserir texto vindo da API no innerHTML.
 */
function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
}
function renderizarOficinas(oficinas) {
    if (oficinas.length === 0) {
        grade.innerHTML = '';
        estadoVazio.style.display = 'block';
        return;
    }
    estadoVazio.style.display = 'none';
    grade.innerHTML = oficinas.map(criarCardOficina).join('');
    ligarEventosComparar();
    atualizarBarraComparar();
}
/**
 * Decide qual endpoint chamar: se houver filtros estruturados ativos
 * (serviço, preço, avaliação), usa filter_oficinas.php (que também aceita
 * o termo de busca "q"); caso contrário, usa search_oficinas.php.
 */

/**
 * "Melhores opções para o seu perfil" (logado) / "Destaques" (visitante):
 * ranking ponderado de backend/oficinas/recomendar_oficinas.php, usando o
 * serviço e a localização já escolhidos nos filtros. Não depende dos
 * outros filtros (preço, avaliação mínima, termo de busca).
 */
async function carregarRecomendadas() {
    const parametros = new URLSearchParams({ limite: '3' });
    if (selectServico.value !== '') parametros.set('servico', selectServico.value);
    if (localizacaoUsuario) {
        parametros.set('lat', String(localizacaoUsuario.lat));
        parametros.set('lng', String(localizacaoUsuario.lng));
        parametros.set('raio_km', '15');
    }
    try {
        const resposta = await chamarApi(`/oficinas/recomendar_oficinas.php?${parametros.toString()}`);
        if (resposta.recomendacoes.length === 0) {
            blocoRecomendadas.style.display = 'none';
            return;
        }
        const titulo = resposta.personalizada ? 'Melhores opções para o seu perfil' : 'Destaques';
        blocoRecomendadas.style.display = 'block';
        blocoRecomendadas.innerHTML = `
            <div style="margin: 0 0 20px; padding:14px 16px; background:#EEF6FF; border-radius: var(--raio-botao);">
                <p style="font-weight:700; margin:0 0 10px;">⭐ ${titulo}</p>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    ${resposta.recomendacoes.map((r, i) => `
                        <a href="oficina.html?id=${r.id_oficina}" style="display:block; background:#fff; border-radius: var(--raio-botao); padding:10px 12px; text-decoration:none; color:inherit;">
                            <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                                <strong>${i === 0 ? '🥇 ' : ''}${escapeHtml(r.nome)}${r.verificada ? ' <span style="color: var(--azul-mecon);">✔</span>' : ''}</strong>
                                <span title="Compatibilidade calculada por nota, preço, distância, confiança e seu histórico" style="color: var(--azul-mecon); font-weight:700;">${r.score}/100</span>
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">
                                ${r.motivos.map((m) => `<span class="tag-servico">${escapeHtml(m)}</span>`).join('')}
                            </div>
                        </a>
                    `).join('')}
                </div>
            </div>`;
    } catch (erro) {
        blocoRecomendadas.style.display = 'none';
    }
}

async function buscarOficinas(pagina = 1) {
    paginaAtualOficinas = pagina;
    const termo = inputBusca.value.trim();
    const servico = selectServico.value;
    const avaliacaoMin = selectAvaliacao.value;
    const precoMin = inputPrecoMin.value;
    const precoMax = inputPrecoMax.value;
    const somenteVerificadas = checkboxVerificadas.checked;
    const temFiltroEstruturado = servico !== '' || avaliacaoMin !== '' || precoMin !== '' || precoMax !== '' || somenteVerificadas || localizacaoUsuario !== null;
    const parametros = new URLSearchParams();
    if (termo !== '') parametros.set('q', termo);
    parametros.set('pagina', String(pagina));
    let caminho;
    if (temFiltroEstruturado) {
        if (servico !== '') parametros.set('servico', servico);
        if (avaliacaoMin !== '') parametros.set('avaliacao_min', avaliacaoMin);
        if (precoMin !== '') parametros.set('preco_min', precoMin);
        if (precoMax !== '') parametros.set('preco_max', precoMax);
        if (somenteVerificadas) parametros.set('somente_verificadas', '1');
        if (localizacaoUsuario) {
            parametros.set('lat', String(localizacaoUsuario.lat));
            parametros.set('lng', String(localizacaoUsuario.lng));
            parametros.set('raio_km', '15');
        }
        caminho = `/oficinas/filter_oficinas.php?${parametros.toString()}`;
    } else {
        caminho = `/oficinas/search_oficinas.php?${parametros.toString()}`;
    }
    try {
        const resposta = await chamarApi(caminho);
        renderizarOficinas(resposta.oficinas);
        renderizarControlesPaginacao(paginacaoContainer, resposta.paginacao, buscarOficinas);
        if (pagina === 1) carregarRecomendadas();
    } catch (erro) {
        console.error('Erro ao buscar oficinas:', erro);
        grade.innerHTML = `<p style="grid-column:1/-1; color:#b00020; padding:12px; background:#fdecea; border-radius:8px;">Não foi possível carregar as oficinas: ${escapeHtml(erro.message)}</p>`;
        estadoVazio.style.display = 'none';
        paginacaoContainer.innerHTML = '';
    }
}
async function carregarServicos() {
    try {
        const resposta = await chamarApi('/servicos/list_servicos.php');
        resposta.servicos.forEach((servico) => {
            const option = document.createElement('option');
            option.value = servico.id_servico;
            option.textContent = servico.nome;
            selectServico.appendChild(option);
        });
    } catch (erro) {
        console.error('Erro ao carregar serviços:', erro);
    }
}
/**
 * Lê os parâmetros da URL (ex: vindos da home) e pré-preenche os campos
 * antes da primeira busca.
 */
function aplicarParametrosDaUrl() {
    const params = new URLSearchParams(window.location.search);
    if (params.has('q')) inputBusca.value = params.get('q');
    if (params.has('servico')) selectServico.value = params.get('servico');
    if (params.has('avaliacao_min')) selectAvaliacao.value = params.get('avaliacao_min');
    if (params.has('preco_min')) inputPrecoMin.value = params.get('preco_min');
    if (params.has('preco_max')) inputPrecoMax.value = params.get('preco_max');
}
document.getElementById('form-busca').addEventListener('submit', (evento) => {
    evento.preventDefault();
    buscarOficinas(1);
});
[selectServico, selectAvaliacao].forEach((select) => {
    select.addEventListener('change', () => buscarOficinas(1));
});
[inputPrecoMin, inputPrecoMax].forEach((input) => {
    input.addEventListener('change', () => buscarOficinas(1));
});
checkboxVerificadas.addEventListener('change', () => buscarOficinas(1));
botaoLocalizacao.addEventListener('click', () => {
    if (!navigator.geolocation) {
        alert('Seu navegador não suporta geolocalização.');
        return;
    }
    botaoLocalizacao.disabled = true;
    botaoLocalizacao.textContent = 'Obtendo localização…';
    navigator.geolocation.getCurrentPosition(
        (posicao) => {
            localizacaoUsuario = { lat: posicao.coords.latitude, lng: posicao.coords.longitude };
            botaoLocalizacao.textContent = '📍 Perto de você (ativo)';
            botaoLocalizacao.disabled = false;
            buscarOficinas(1);
        },
        () => {
            alert('Não foi possível obter sua localização. Verifique a permissão do navegador.');
            botaoLocalizacao.textContent = '📍 Usar minha localização';
            botaoLocalizacao.disabled = false;
        }
    );
});
(async function inicializar() {
    await carregarServicos();
    aplicarParametrosDaUrl();
    buscarOficinas();
})();
