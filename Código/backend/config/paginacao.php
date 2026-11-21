<?php
/**
 * MecOn - helpers de paginação
 *
 * Usado por qualquer listagem que possa crescer sem limite (list_usuarios,
 * list_all_reviews, search_oficinas, filter_oficinas). Parâmetros de query
 * aceitos por essas rotas:
 *   pagina     -> página desejada, começando em 1 (padrão: 1)
 *   por_pagina -> itens por página (padrão: 20, máximo: 100 — limite para
 *                 evitar que alguém peça a tabela inteira de uma vez só)
 */

function obterParametrosPaginacao(int $porPaginaPadrao = 20, int $porPaginaMaximo = 100): array
{
    $pagina    = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
    $porPagina = filter_input(INPUT_GET, 'por_pagina', FILTER_VALIDATE_INT) ?: $porPaginaPadrao;
    $pagina    = max(1, $pagina);
    $porPagina = max(1, min($porPaginaMaximo, $porPagina));
    return [
        'pagina'     => $pagina,
        'por_pagina' => $porPagina,
        'offset'     => ($pagina - 1) * $porPagina,
    ];
}

/**
 * Monta o bloco "paginacao" que acompanha o array de itens na resposta JSON.
 */
function metadadosPaginacao(int $pagina, int $porPagina, int $totalRegistros): array
{
    return [
        'pagina_atual'    => $pagina,
        'por_pagina'      => $porPagina,
        'total_registros' => $totalRegistros,
        'total_paginas'   => $totalRegistros > 0 ? (int) ceil($totalRegistros / $porPagina) : 0,
    ];
}
