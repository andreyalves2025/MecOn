-- v2: inclui a seção 17 com +15 avaliações, +12 conversas (+46 mensagens), +15 ordens de serviço (e agendamentos/histórico relacionados).
-- =====================================================================
-- MecOn - Carga de dados completa para teste (as 23 tabelas)
-- Compatível com database/schema.sql (versão final).
-- Execute no MySQL Workbench com o banco `mecon` já criado.
--
-- LOGINS (senha de TODOS: Teste@123)
--   admin   : admin@example.com
--   clientes: ana.souza@example.com | bruno.lima@example.com | carla.mendes@example.com
--   oficinas: autocenter.pinhais@example.com | mecanica.emiliano@example.com |
--             oficina.portao@example.com | speedcar.boqueirao@example.com |
--             garagem.aguaverde@example.com
--
-- É re-executável: apaga antes apenas os registros @example.com e recria.
-- As datas são relativas a hoje (NOW()), então o histórico nunca "envelhece".
-- =====================================================================
SET NAMES utf8mb4;
SET SQL_SAFE_UPDATES = 0;

-- ---------- 0) Limpeza dos dados de teste anteriores ----------
DELETE FROM log_auditoria WHERE id_ator IN (SELECT id_usuario FROM usuarios WHERE email LIKE '%@example.com');
DELETE FROM tentativas_login WHERE email LIKE '%@example.com';
-- Agendamentos/OS sobrevivem à exclusão do usuário (FK SET NULL) e as oficinas têm RESTRICT:
-- por isso são apagados explicitamente das oficinas de teste (OS caem em cascata).
DELETE FROM agendamentos WHERE id_oficina IN (SELECT id_oficina FROM oficinas WHERE email LIKE '%@example.com');
DELETE FROM usuarios WHERE email LIKE '%@example.com';   -- cascata: veículos, avaliações, chats...
DELETE FROM agendamentos WHERE id_usuario IS NULL AND id_oficina IN (SELECT id_oficina FROM oficinas WHERE email LIKE '%@example.com');
DELETE FROM oficinas WHERE email LIKE '%@example.com';   -- cascata: serviços da oficina, promoções, orçamentos...

SET @hash = '$2y$10$pgGrw/z5oomde51WM6hyhuLZY2lVUzfNIyGVsOjb4Fu5S9mpRnPPO';  -- Teste@123

-- ---------- 1) Catálogo de serviços ----------
INSERT IGNORE INTO servicos (nome, intervalo_recomendado_meses, intervalo_recomendado_km) VALUES
 ('Troca de óleo', 6, 10000),
 ('Alinhamento e balanceamento', 6, 10000),
 ('Revisão de freios', 12, 20000),
 ('Troca de bateria', 24, NULL),
 ('Revisão completa', 12, 15000),
 ('Suspensão', 12, 30000),
 ('Ar-condicionado', 12, NULL),
 ('Elétrica automotiva', 12, NULL);

SET @s_oleo  = (SELECT id_servico FROM servicos WHERE nome = 'Troca de óleo');
SET @s_alin  = (SELECT id_servico FROM servicos WHERE nome = 'Alinhamento e balanceamento');
SET @s_freio = (SELECT id_servico FROM servicos WHERE nome = 'Revisão de freios');
SET @s_bat   = (SELECT id_servico FROM servicos WHERE nome = 'Troca de bateria');
SET @s_rev   = (SELECT id_servico FROM servicos WHERE nome = 'Revisão completa');
SET @s_susp  = (SELECT id_servico FROM servicos WHERE nome = 'Suspensão');
SET @s_ar    = (SELECT id_servico FROM servicos WHERE nome = 'Ar-condicionado');
SET @s_ele   = (SELECT id_servico FROM servicos WHERE nome = 'Elétrica automotiva');

-- ---------- 2) Usuários (1 admin + 3 clientes) ----------
INSERT INTO usuarios (nome, email, senha_hash, email_verificado, termos_aceitos_em, termos_versao, tipo, data_cadastro) VALUES
 ('Administrador MecOn', 'admin@example.com',        @hash, 1, NOW(), '1.0', 'admin',   DATE_SUB(NOW(), INTERVAL 400 DAY)),
 ('Ana Souza',           'ana.souza@example.com',    @hash, 1, NOW(), '1.0', 'cliente', DATE_SUB(NOW(), INTERVAL 360 DAY)),
 ('Bruno Lima',          'bruno.lima@example.com',   @hash, 1, NOW(), '1.0', 'cliente', DATE_SUB(NOW(), INTERVAL 300 DAY)),
 ('Carla Mendes',        'carla.mendes@example.com', @hash, 1, NOW(), '1.0', 'cliente', DATE_SUB(NOW(), INTERVAL 120 DAY));
SET @adm   = (SELECT id_usuario FROM usuarios WHERE email = 'admin@example.com');
SET @ana   = (SELECT id_usuario FROM usuarios WHERE email = 'ana.souza@example.com');
SET @bruno = (SELECT id_usuario FROM usuarios WHERE email = 'bruno.lima@example.com');
SET @carla = (SELECT id_usuario FROM usuarios WHERE email = 'carla.mendes@example.com');

-- ---------- 3) Oficinas (3 verificadas, 2 não) ----------
INSERT INTO oficinas (nome, email, senha_hash, cidade, bairro, endereco, latitude, longitude, telefone, descricao, verificada, verificada_em, data_cadastro) VALUES
 ('Auto Center Pinhais', 'autocenter.pinhais@example.com', @hash, 'Pinhais', 'Centro', 'Rua Teste Um, 100', -25.4443000, -49.1925000, '(41) 99000-0001',
  'Mecânica geral e revisão preventiva com atendimento por hora marcada.', 1, DATE_SUB(NOW(), INTERVAL 200 DAY), DATE_SUB(NOW(), INTERVAL 380 DAY)),
 ('Mecânica Emiliano', 'mecanica.emiliano@example.com', @hash, 'Pinhais', 'Emiliano Perneta', 'Av. Teste Dois, 250', -25.4310000, -49.1860000, '(41) 99000-0002',
  'Especializada em freios, suspensão e alinhamento.', 1, DATE_SUB(NOW(), INTERVAL 150 DAY), DATE_SUB(NOW(), INTERVAL 350 DAY)),
 ('Oficina do Portão', 'oficina.portao@example.com', @hash, 'Curitiba', 'Portão', 'Rua Teste Três, 330', -25.4770000, -49.2960000, '(41) 99000-0003',
  'Oficina familiar com foco em injeção eletrônica e parte elétrica.', 1, DATE_SUB(NOW(), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 330 DAY)),
 ('Speed Car Boqueirão', 'speedcar.boqueirao@example.com', @hash, 'Curitiba', 'Boqueirão', 'Rua Teste Quatro, 410', -25.5050000, -49.2350000, '(41) 99000-0004',
  'Troca de óleo rápida, pneus e ar-condicionado.', 0, NULL, DATE_SUB(NOW(), INTERVAL 250 DAY)),
 ('Garagem Água Verde', 'garagem.aguaverde@example.com', @hash, 'Curitiba', 'Água Verde', 'Av. Teste Cinco, 520', -25.4540000, -49.2810000, '(41) 99000-0005',
  'Revisões completas e manutenção de carros nacionais e importados.', 0, NULL, DATE_SUB(NOW(), INTERVAL 220 DAY));
SET @o1 = (SELECT id_oficina FROM oficinas WHERE email = 'autocenter.pinhais@example.com');
SET @o2 = (SELECT id_oficina FROM oficinas WHERE email = 'mecanica.emiliano@example.com');
SET @o3 = (SELECT id_oficina FROM oficinas WHERE email = 'oficina.portao@example.com');
SET @o4 = (SELECT id_oficina FROM oficinas WHERE email = 'speedcar.boqueirao@example.com');
SET @o5 = (SELECT id_oficina FROM oficinas WHERE email = 'garagem.aguaverde@example.com');

-- ---------- 4) Serviços e preços de cada oficina ----------
INSERT INTO oficina_servico (id_oficina, id_servico, preco) VALUES
 (@o1, @s_oleo, 140.00), (@o1, @s_alin, 120.00), (@o1, @s_rev, 480.00),
 (@o2, @s_freio, 220.00), (@o2, @s_susp, 350.00), (@o2, @s_alin, 110.00),
 (@o3, @s_ele, 180.00), (@o3, @s_bat, 90.00), (@o3, @s_oleo, 130.00),
 (@o4, @s_oleo, 120.00), (@o4, @s_ar, 250.00), (@o4, @s_alin, 100.00),
 (@o5, @s_rev, 520.00), (@o5, @s_freio, 240.00), (@o5, @s_oleo, 160.00);

-- ---------- 5) Promoções (ativas, expirada e futura) ----------
INSERT INTO promocoes (id_oficina, id_servico, titulo, descricao, data_inicio, data_fim) VALUES
 (@o1, @s_oleo,  'Troca de óleo com 15% de desconto', 'Válido para óleo sintético 5W30, filtro incluso.', DATE_SUB(CURDATE(), INTERVAL 5 DAY),  DATE_ADD(CURDATE(), INTERVAL 20 DAY)),
 (@o2, @s_freio, 'Revisão de freios + teste grátis',   'Teste de fluido de freio sem custo na revisão.',    DATE_SUB(CURDATE(), INTERVAL 2 DAY),  DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
 (@o3, @s_ele,   'Check-up elétrico grátis',           'Promoção encerrada.',                                DATE_SUB(CURDATE(), INTERVAL 40 DAY), DATE_SUB(CURDATE(), INTERVAL 10 DAY)),
 (@o4, NULL,     '10% off na primeira visita',         'Vale para qualquer serviço do catálogo.',            DATE_SUB(CURDATE(), INTERVAL 1 DAY),  DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
 (@o5, @s_rev,   'Revisão completa de verão',          'Começa na próxima semana.',                          DATE_ADD(CURDATE(), INTERVAL 7 DAY),  DATE_ADD(CURDATE(), INTERVAL 30 DAY));

-- ---------- 6) Veículos ----------
INSERT INTO veiculos (id_usuario, placa, km_atual, data_cadastro) VALUES
 (@ana,   'ABC1D23', 48900, DATE_SUB(NOW(), INTERVAL 350 DAY));
SET @v1 = LAST_INSERT_ID();
INSERT INTO veiculos (id_usuario, placa, km_atual, data_cadastro) VALUES
 (@bruno, 'QWE4R56', 91800, DATE_SUB(NOW(), INTERVAL 290 DAY));
SET @v2 = LAST_INSERT_ID();
INSERT INTO veiculos (id_usuario, placa, km_atual, data_cadastro) VALUES
 (@bruno, 'RTY7U89', 30900, DATE_SUB(NOW(), INTERVAL 210 DAY));
SET @v3 = LAST_INSERT_ID();
INSERT INTO veiculos (id_usuario, placa, km_atual, data_cadastro) VALUES
 (@carla, 'JKL2M34', 15900, DATE_SUB(NOW(), INTERVAL 110 DAY));
SET @v4 = LAST_INSERT_ID();

-- ---------- 7) Pedidos de orçamento, orçamentos e anexos ----------
-- P1 (Bruno / suspensão) -> APROVADO na Mecânica Emiliano
INSERT INTO pedidos_orcamento (id_usuario, id_veiculo, id_servico, descricao, status, criado_em) VALUES
 (@bruno, @v2, @s_susp, 'Barulho na suspensão dianteira ao passar em buracos e carro puxando levemente para a direita.', 'aprovado', DATE_SUB(NOW(), INTERVAL 8 DAY));
SET @p1 = LAST_INSERT_ID();
INSERT INTO orcamentos (id_pedido, id_oficina, status, valor_total, valor_pecas, valor_mao_obra, prazo_dias, validade_ate, detalhes, respondido_em, criado_em) VALUES
 (@p1, @o2, 'aprovado', 640.00, 420.00, 220.00, 2, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'Troca de 2 amortecedores dianteiros e buchas da bandeja. Garantia de 6 meses.', DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY));
SET @or_p1_o2 = LAST_INSERT_ID();
INSERT INTO orcamentos (id_pedido, id_oficina, status, valor_total, valor_pecas, valor_mao_obra, prazo_dias, validade_ate, detalhes, respondido_em, criado_em) VALUES
 (@p1, @o5, 'nao_escolhido', 720.00, 480.00, 240.00, 3, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Peças de marca original, prazo de 3 dias.', DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY));
INSERT INTO orcamentos (id_pedido, id_oficina, status, valor_total, valor_pecas, valor_mao_obra, prazo_dias, validade_ate, detalhes, respondido_em, criado_em) VALUES
 (@p1, @o3, 'recusado', NULL, NULL, NULL, NULL, NULL, 'No momento não trabalhamos com suspensão.', DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY));
INSERT INTO anexos_pedido (id_pedido, nome_original, nome_arquivo, tipo_mime, tamanho_bytes, criado_em) VALUES
 (@p1, 'suspensao_dianteira.jpg', CONCAT(LEFT(SHA2('seed-anexo-1', 256), 60), '.jpg'), 'image/jpeg', 184320, DATE_SUB(NOW(), INTERVAL 8 DAY)),
 (@p1, 'barulho_no_buraco.mp4',   CONCAT(LEFT(SHA2('seed-anexo-2', 256), 60), '.mp4'), 'video/mp4', 2457600, DATE_SUB(NOW(), INTERVAL 8 DAY));

-- P2 (Ana / revisão completa) -> ABERTO, aguardando escolha
INSERT INTO pedidos_orcamento (id_usuario, id_veiculo, id_servico, descricao, status, criado_em) VALUES
 (@ana, @v1, @s_rev, 'Revisão completa dos 50 mil km. Quero saber valores e prazos.', 'aberto', DATE_SUB(NOW(), INTERVAL 2 DAY));
SET @p2 = LAST_INSERT_ID();
INSERT INTO orcamentos (id_pedido, id_oficina, status, valor_total, valor_pecas, valor_mao_obra, prazo_dias, validade_ate, detalhes, respondido_em, criado_em) VALUES
 (@p2, @o1, 'respondido', 480.00, 260.00, 220.00, 1, DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'Inclui óleo, filtros (óleo, ar, cabine) e checklist de 30 itens.', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
 (@p2, @o5, 'respondido', 540.00, 290.00, 250.00, 2, DATE_ADD(CURDATE(), INTERVAL 9 DAY),  'Checklist completo com scanner e relatório digital.',        DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY));
INSERT INTO orcamentos (id_pedido, id_oficina, status, criado_em) VALUES
 (@p2, @o3, 'pendente', DATE_SUB(NOW(), INTERVAL 2 DAY));
INSERT INTO anexos_pedido (id_pedido, nome_original, nome_arquivo, tipo_mime, tamanho_bytes, criado_em) VALUES
 (@p2, 'painel_km.jpg', CONCAT(LEFT(SHA2('seed-anexo-3', 256), 60), '.jpg'), 'image/jpeg', 98304, DATE_SUB(NOW(), INTERVAL 2 DAY));

-- P3 (Carla / ar-condicionado) -> CANCELADO
INSERT INTO pedidos_orcamento (id_usuario, id_veiculo, id_servico, descricao, status, criado_em) VALUES
 (@carla, @v4, @s_ar, 'Ar-condicionado soprando pouco gelado.', 'cancelado', DATE_SUB(NOW(), INTERVAL 15 DAY));
SET @p3 = LAST_INSERT_ID();
INSERT INTO orcamentos (id_pedido, id_oficina, status, valor_total, valor_pecas, valor_mao_obra, prazo_dias, validade_ate, detalhes, respondido_em, criado_em) VALUES
 (@p3, @o4, 'cancelado', 250.00, 120.00, 130.00, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Higienização + recarga de gás.', DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY));

-- ---------- 8) Agendamentos ----------
-- Concluídos (histórico real do site)
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@ana, @o1, @s_oleo, @v1, DATE_SUB(NOW(), INTERVAL 150 DAY), 'Troca de óleo e filtro.', 'concluido', 'Confirmado, pode vir no horário.', DATE_SUB(NOW(), INTERVAL 152 DAY), 207.00, DATE_SUB(NOW(), INTERVAL 153 DAY));
SET @a1 = LAST_INSERT_ID();
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@ana, @o2, @s_freio, @v1, DATE_SUB(NOW(), INTERVAL 90 DAY), 'Freio fazendo ruído ao frear.', 'concluido', 'Aceito. Traga o carro pela manhã.', DATE_SUB(NOW(), INTERVAL 92 DAY), 220.00, DATE_SUB(NOW(), INTERVAL 93 DAY));
SET @a2 = LAST_INSERT_ID();
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@bruno, @o3, @s_bat, @v2, DATE_SUB(NOW(), INTERVAL 120 DAY), 'Carro não pega de manhã.', 'concluido', 'Aceito, levamos bateria nova.', DATE_SUB(NOW(), INTERVAL 121 DAY), 420.00, DATE_SUB(NOW(), INTERVAL 122 DAY));
SET @a3 = LAST_INSERT_ID();
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@bruno, @o5, @s_rev, @v2, DATE_SUB(NOW(), INTERVAL 45 DAY), 'Revisão antes de viajar.', 'concluido', 'Confirmado.', DATE_SUB(NOW(), INTERVAL 47 DAY), 520.00, DATE_SUB(NOW(), INTERVAL 48 DAY));
SET @a4 = LAST_INSERT_ID();
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@carla, @o1, @s_alin, @v4, DATE_SUB(NOW(), INTERVAL 60 DAY), 'Volante tremendo a 80 km/h.', 'concluido', 'Aceito.', DATE_SUB(NOW(), INTERVAL 61 DAY), 120.00, DATE_SUB(NOW(), INTERVAL 62 DAY));
SET @a5 = LAST_INSERT_ID();
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@carla, @o4, @s_oleo, @v4, DATE_SUB(NOW(), INTERVAL 20 DAY), 'Troca de óleo.', 'concluido', 'Aceito.', DATE_SUB(NOW(), INTERVAL 21 DAY), 174.00, DATE_SUB(NOW(), INTERVAL 22 DAY));
SET @a6 = LAST_INSERT_ID();

-- Em andamento / futuros / recusado / cancelado
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, criado_em) VALUES
 (@ana, @o1, @s_rev, @v1, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 3 DAY), INTERVAL 9 HOUR), 'Prefiro de manhã, se possível.', 'pendente', DATE_SUB(NOW(), INTERVAL 1 DAY));
SET @a7 = LAST_INSERT_ID();
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, id_orcamento, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@bruno, @o2, @s_susp, @v2, @or_p1_o2, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 2 DAY), INTERVAL 14 HOUR), 'Conforme orçamento aprovado.', 'aceito', 'Pode trazer às 14h. Ficará pronto em 2 dias.', DATE_SUB(NOW(), INTERVAL 6 DAY), 640.00, DATE_SUB(NOW(), INTERVAL 6 DAY));
SET @a8 = LAST_INSERT_ID();
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, criado_em) VALUES
 (@bruno, @o3, @s_ele, @v3, DATE_ADD(CURDATE(), INTERVAL 8 HOUR), 'Luz da bateria acendendo no painel.', 'aceito', 'Aceito. Pode deixar o carro hoje cedo.', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY));
SET @a9 = LAST_INSERT_ID();
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, criado_em) VALUES
 (@carla, @o5, @s_freio, @v4, DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 5 DAY), INTERVAL 10 HOUR), 'Pastilhas gastas.', 'recusado', 'Sem agenda nessa data. Tente a semana seguinte.', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY));
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, criado_em) VALUES
 (@ana, @o4, @s_ar, @v1, DATE_SUB(NOW(), INTERVAL 10 DAY), 'Cancelei porque resolvi em outra oficina.', 'cancelado', DATE_SUB(NOW(), INTERVAL 14 DAY));

-- ---------- 9) Ordens de serviço ----------
-- OS entregue: A1
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a1, @o1, 'Marcos (mecânico)', 38200, 'entregue', 'Óleo vencido e filtro saturado.', 'Veículo entregue limpo e com etiqueta de próxima troca.', 60.00, NULL, DATE_SUB(NOW(), INTERVAL 150 DAY), DATE_SUB(NOW(), INTERVAL 150 DAY));
SET @os1 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os1, 'Óleo 5W30 sintético (litro)', 4.00, 28.00),
 (@os1, 'Filtro de óleo', 1.00, 35.00);
-- OS entregue: A3
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a3, @o3, 'Seu Antônio', 85000, 'entregue', 'Bateria sem carga (sulfatada). Alternador normal.', 'Garantia de 12 meses na bateria.', 40.00, NULL, DATE_SUB(NOW(), INTERVAL 120 DAY), DATE_SUB(NOW(), INTERVAL 120 DAY));
SET @os3 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os3, 'Bateria 60Ah', 1.00, 380.00);
-- OS entregue: A4 (com desconto)
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a4, @o5, 'Rafael', 89700, 'entregue', 'Revisão preventiva sem anormalidades graves.', 'Recomendada troca de pneus em 10 mil km.', 340.00, 20.00, DATE_SUB(NOW(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 44 DAY));
SET @os4 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os4, 'Óleo 5W30 sintético (litro)', 4.00, 30.00),
 (@os4, 'Filtro de óleo', 1.00, 35.00),
 (@os4, 'Filtro de ar', 1.00, 45.00);
-- OS em execução hoje: A9
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, criado_em) VALUES
 (@a9, @o3, 'Seu Antônio', 30850, 'aguardando_peca', 'Alternador com carga baixa. Necessário trocar regulador de tensão.', 'Peça solicitada ao fornecedor; previsão amanhã.', 120.00, NOW());
SET @os9 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os9, 'Regulador de tensão do alternador', 1.00, 160.00);

-- ---------- 10) Histórico de manutenções (+ peças) ----------
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v1, @o1, @s_oleo, DATE_SUB(CURDATE(), INTERVAL 150 DAY), 38200, 207.00, 147.00, 60.00);
SET @h1 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h1, 'Óleo 5W30 sintético (litro)', 4.00, 28.00), (@h1, 'Filtro de óleo', 1.00, 35.00);

INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v1, @o2, @s_freio, DATE_SUB(CURDATE(), INTERVAL 90 DAY), 42500, 220.00, 130.00, 90.00);
SET @h2 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h2, 'Jogo de pastilhas dianteiras', 1.00, 130.00);

INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v2, @o3, @s_bat, DATE_SUB(CURDATE(), INTERVAL 120 DAY), 85000, 420.00, 380.00, 40.00);
SET @h3 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h3, 'Bateria 60Ah', 1.00, 380.00);

INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v2, @o5, @s_rev, DATE_SUB(CURDATE(), INTERVAL 45 DAY), 89700, 520.00, 200.00, 320.00);
SET @h4 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h4, 'Óleo 5W30 sintético (litro)', 4.00, 30.00), (@h4, 'Filtro de óleo', 1.00, 35.00), (@h4, 'Filtro de ar', 1.00, 45.00);

INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v4, @o1, @s_alin, DATE_SUB(CURDATE(), INTERVAL 60 DAY), 14200, 120.00, 0.00, 120.00);
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v4, @o4, @s_oleo, DATE_SUB(CURDATE(), INTERVAL 20 DAY), 15500, 174.00, 134.00, 40.00);
SET @h6 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h6, 'Óleo 5W30 semissintético (litro)', 4.00, 26.00), (@h6, 'Filtro de óleo', 1.00, 30.00);

-- Registros manuais antigos (geram lembretes de manutenção vencida)
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v2, @o3, @s_oleo, DATE_SUB(CURDATE(), INTERVAL 230 DAY), 80100, 190.00, 130.00, 60.00);
SET @h9 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h9, 'Óleo 5W30 sintético (litro)', 4.00, 25.00), (@h9, 'Filtro de óleo', 1.00, 30.00);
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v3, @o4, @s_alin, DATE_SUB(CURDATE(), INTERVAL 200 DAY), 28000, 100.00, 0.00, 100.00);

-- ---------- 11) Avaliações (com e sem agendamento vinculado) ----------
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, resposta_oficina, data_resposta, data_avaliacao) VALUES
 (@ana, @o1, @a1, 5, 'Atendimento rápido e preço justo. Explicaram tudo antes de começar.', 'Obrigado, Ana! Até a próxima revisão.', DATE_SUB(NOW(), INTERVAL 148 DAY), DATE_SUB(NOW(), INTERVAL 149 DAY));
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, data_avaliacao) VALUES
 (@ana, @o2, @a2, 4, 'Resolveram o ruído do freio. Só demorou um pouco mais que o combinado.', DATE_SUB(NOW(), INTERVAL 88 DAY));
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, resposta_oficina, data_resposta, data_avaliacao) VALUES
 (@bruno, @o3, @a3, 5, 'Trocaram a bateria na hora e deram garantia por escrito.', 'Valeu, Bruno! Qualquer coisa é só chamar.', DATE_SUB(NOW(), INTERVAL 118 DAY), DATE_SUB(NOW(), INTERVAL 119 DAY));
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, data_avaliacao) VALUES
 (@bruno, @o5, @a4, 4, 'Revisão completa e relatório digital. Ambiente limpo e organizado.', DATE_SUB(NOW(), INTERVAL 43 DAY));
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, data_avaliacao) VALUES
 (@carla, @o1, @a5, 5, 'O volante parou de tremer. Recomendo!', DATE_SUB(NOW(), INTERVAL 58 DAY));
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, resposta_oficina, data_resposta, data_avaliacao) VALUES
 (@carla, @o4, @a6, 2, 'Demorou muito e o valor final ficou acima do combinado.', 'Lamentamos, Carla. A diferença foi a troca do filtro, que não estava no orçamento inicial. Fique à vontade para falar conosco.', DATE_SUB(NOW(), INTERVAL 17 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY));
SET @r_carla_o4 = LAST_INSERT_ID();
-- Sem agendamento vinculado (sem o selo "serviço confirmado")
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, data_avaliacao) VALUES
 (@ana, @o3, NULL, 4, 'Fui só pedir uma informação e fui muito bem atendida.', DATE_SUB(NOW(), INTERVAL 30 DAY));
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, data_avaliacao) VALUES
 (@bruno, @o1, NULL, 3, 'Bom atendimento, mas o estacionamento é pequeno.', DATE_SUB(NOW(), INTERVAL 25 DAY));
SET @r_bruno_o1 = LAST_INSERT_ID();

-- ---------- 12) Denúncias de avaliações ----------
INSERT INTO denuncias (id_avaliacao, id_usuario, motivo, status, data_denuncia) VALUES
 (@r_carla_o4, @bruno, 'Comentário parece exagerado e sem provas.', 'pendente',   DATE_SUB(NOW(), INTERVAL 5 DAY)),
 (@r_bruno_o1, @ana,   'Avaliação não trata do serviço prestado.',  'descartada', DATE_SUB(NOW(), INTERVAL 12 DAY));

-- ---------- 13) Favoritos ----------
INSERT INTO favoritos (id_usuario, id_oficina) VALUES
 (@ana, @o1), (@ana, @o3), (@bruno, @o2), (@bruno, @o5), (@carla, @o1);

-- ---------- 14) Chat (conversas e mensagens) ----------
INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@ana, @o1, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));
SET @c1 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@c1, 'usuario', 'Olá! A revisão de 50 mil km inclui troca do filtro de cabine?', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
 (@c1, 'oficina', 'Olá, Ana! Inclui sim: óleo, filtros de óleo, ar e cabine.',    DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
 (@c1, 'usuario', 'Perfeito, vou agendar para o início da semana.',               DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@bruno, @o2, DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 3 HOUR));
SET @c2 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@c2, 'usuario', 'Boa tarde! Consigo levar o carro na quinta?',                      DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY)),
 (@c2, 'oficina', 'Boa tarde, Bruno. Quinta às 14h está livre.',                      DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY)),
 (@c2, 'usuario', 'Fechado. Preciso levar algum documento?',                          DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY)),
 (@c2, 'oficina', 'Só o documento do veículo. Tudo certo para quinta às 14h!',        DATE_SUB(NOW(), INTERVAL 3 HOUR), NULL);

INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@carla, @o5, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 30 MINUTE));
SET @c3 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@c3, 'oficina', 'Carla, infelizmente não temos agenda no dia pedido. Pode na semana seguinte?', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 20 HOUR)),
 (@c3, 'usuario', 'Posso sim, qual dia teriam livre?',                                           DATE_SUB(NOW(), INTERVAL 30 MINUTE), NULL);

-- ---------- 15) Segurança: tentativas de login e token de e-mail ----------
INSERT INTO tentativas_login (email, tentativas, atualizado_em, bloqueado_ate) VALUES
 ('carla.mendes@example.com', 2, NOW(), NULL);
INSERT INTO tokens_verificacao_email (token, id_usuario, expira_em, criado_em) VALUES
 (SHA2('mecon-seed-token-carla', 256), @carla, DATE_ADD(NOW(), INTERVAL 1 DAY), NOW());

-- ---------- 16) Auditoria do admin ----------
INSERT INTO log_auditoria (tipo_ator, id_ator, acao, entidade, id_entidade, detalhes, criado_em) VALUES
 ('admin', @adm, 'verificar_oficina', 'oficina', @o1, 'Selo de verificação concedido.', DATE_SUB(NOW(), INTERVAL 200 DAY));
SET @l1 = LAST_INSERT_ID();
INSERT INTO log_auditoria_detalhe (id_log, campo, valor_anterior, valor_novo) VALUES (@l1, 'verificada', '0', '1');
INSERT INTO log_auditoria (tipo_ator, id_ator, acao, entidade, id_entidade, detalhes, criado_em) VALUES
 ('admin', @adm, 'verificar_oficina', 'oficina', @o2, 'Selo de verificação concedido.', DATE_SUB(NOW(), INTERVAL 150 DAY));
SET @l2 = LAST_INSERT_ID();
INSERT INTO log_auditoria_detalhe (id_log, campo, valor_anterior, valor_novo) VALUES (@l2, 'verificada', '0', '1');
INSERT INTO log_auditoria (tipo_ator, id_ator, acao, entidade, id_entidade, detalhes, criado_em) VALUES
 ('admin', @adm, 'verificar_oficina', 'oficina', @o3, 'Selo de verificação concedido.', DATE_SUB(NOW(), INTERVAL 90 DAY));
SET @l3 = LAST_INSERT_ID();
INSERT INTO log_auditoria_detalhe (id_log, campo, valor_anterior, valor_novo) VALUES (@l3, 'verificada', '0', '1');
INSERT INTO log_auditoria (tipo_ator, id_ator, acao, entidade, id_entidade, detalhes, criado_em) VALUES
 ('admin', @adm, 'resolver_denuncia', 'denuncia', (SELECT id_denuncia FROM denuncias WHERE id_avaliacao = @r_bruno_o1 LIMIT 1), 'Denúncia descartada: avaliação adequada.', DATE_SUB(NOW(), INTERVAL 11 DAY));
SET @l4 = LAST_INSERT_ID();
INSERT INTO log_auditoria_detalhe (id_log, campo, valor_anterior, valor_novo) VALUES (@l4, 'status', 'pendente', 'descartada');


-- =====================================================================
-- 17) EXPANSÃO DA CARGA DEMO (v2): mais avaliações, comentários, chats e OS
--     Mantém os usuários e oficinas acima; só acrescenta dados relacionados.
--     Cada serviço concluído abaixo gera: agendamento + OS entregue + itens
--     + histórico/peças + avaliação (com o selo "serviço confirmado").
-- =====================================================================

-- n1: ana em oficina 3 (oleo), há 60 dias
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@ana, @o3, @s_oleo, @v1, DATE_SUB(NOW(), INTERVAL 60 DAY), 'Troca de óleo e filtro, por favor.', 'concluido', 'Confirmado, pode vir no horário.', DATE_SUB(NOW(), INTERVAL 62 DAY), 166.00, DATE_SUB(NOW(), INTERVAL 63 DAY));
SET @a_n1 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a_n1, @o3, 'Seu Antônio', 43800, 'entregue', 'Óleo escurecido; sem vazamentos.', 'Próxima troca em 10 mil km.', 40.00, NULL, DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 60 DAY));
SET @os_n1 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os_n1, 'Óleo 5W30 semissintético (litro)', 4.00, 24.00),
 (@os_n1, 'Filtro de óleo', 1.00, 30.00);
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v1, @o3, @s_oleo, DATE_SUB(CURDATE(), INTERVAL 60 DAY), 43800, 166.00, 126.00, 40.00);
SET @h_n1 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h_n1, 'Óleo 5W30 semissintético (litro)', 4.00, 24.00),
 (@h_n1, 'Filtro de óleo', 1.00, 30.00);
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, resposta_oficina, data_resposta, data_avaliacao) VALUES
 (@ana, @o3, @a_n1, 5, 'Ótimo atendimento do Seu Antônio e preço melhor que o da concessionária.', 'Obrigado, Ana! Volte sempre.', DATE_SUB(NOW(), INTERVAL 58 DAY), DATE_SUB(NOW(), INTERVAL 59 DAY));

-- n2: ana em oficina 2 (alin), há 30 dias
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@ana, @o2, @s_alin, @v1, DATE_SUB(NOW(), INTERVAL 30 DAY), 'Carro puxando para a esquerda.', 'concluido', 'Confirmado, pode vir no horário.', DATE_SUB(NOW(), INTERVAL 32 DAY), 110.00, DATE_SUB(NOW(), INTERVAL 33 DAY));
SET @a_n2 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a_n2, @o2, 'Edson', 46500, 'entregue', 'Convergência fora do especificado; balanceamento das 4 rodas.', 'Alinhamento refeito e rodas balanceadas.', 110.00, NULL, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 30 DAY));
SET @os_n2 = LAST_INSERT_ID();
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v1, @o2, @s_alin, DATE_SUB(CURDATE(), INTERVAL 30 DAY), 46500, 110.00, 0.00, 110.00);
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, resposta_oficina, data_resposta, data_avaliacao) VALUES
 (@ana, @o2, @a_n2, 4, 'Serviço bem feito, mas esperei uns 40 minutos além do combinado.', 'Obrigado pelo retorno, Ana. Vamos melhorar a pontualidade.', DATE_SUB(NOW(), INTERVAL 28 DAY), DATE_SUB(NOW(), INTERVAL 29 DAY));

-- n3: ana em oficina 3 (ele), há 12 dias
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@ana, @o3, @s_ele, @v1, DATE_SUB(NOW(), INTERVAL 12 DAY), 'Vidro elétrico do motorista parou de funcionar.', 'concluido', 'Confirmado, pode vir no horário.', DATE_SUB(NOW(), INTERVAL 14 DAY), 255.00, DATE_SUB(NOW(), INTERVAL 15 DAY));
SET @a_n3 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a_n3, @o3, 'Seu Antônio', 48200, 'entregue', 'Motor do vidro travado; chicote com mau contato.', 'Garantia de 90 dias na peça.', 80.00, NULL, DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY));
SET @os_n3 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os_n3, 'Motor do vidro elétrico', 1.00, 150.00),
 (@os_n3, 'Chicote de reparo', 1.00, 25.00);
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v1, @o3, @s_ele, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 48200, 255.00, 175.00, 80.00);
SET @h_n3 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h_n3, 'Motor do vidro elétrico', 1.00, 150.00),
 (@h_n3, 'Chicote de reparo', 1.00, 25.00);
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, resposta_oficina, data_resposta, data_avaliacao) VALUES
 (@ana, @o3, @a_n3, 5, 'Resolveram em um dia e explicaram o defeito com calma.', 'Agradecemos, Ana! Qualquer problema é só chamar.', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY));

-- n11: ana em oficina 5 (oleo), há 45 dias
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@ana, @o5, @s_oleo, @v1, DATE_SUB(NOW(), INTERVAL 45 DAY), 'Troca de óleo e filtro.', 'concluido', 'Confirmado, pode vir no horário.', DATE_SUB(NOW(), INTERVAL 47 DAY), 215.00, DATE_SUB(NOW(), INTERVAL 48 DAY));
SET @a_n11 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a_n11, @o5, 'Rafael', 45000, 'entregue', 'Óleo vencido.', 'Veículo entregue com 2 horas de atraso.', 60.00, NULL, DATE_SUB(NOW(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 45 DAY));
SET @os_n11 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os_n11, 'Óleo 5W30 sintético (litro)', 4.00, 30.00),
 (@os_n11, 'Filtro de óleo', 1.00, 35.00);
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v1, @o5, @s_oleo, DATE_SUB(CURDATE(), INTERVAL 45 DAY), 45000, 215.00, 155.00, 60.00);
SET @h_n11 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h_n11, 'Óleo 5W30 sintético (litro)', 4.00, 30.00),
 (@h_n11, 'Filtro de óleo', 1.00, 35.00);
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, resposta_oficina, data_resposta, data_avaliacao) VALUES
 (@ana, @o5, @a_n11, 2, 'Fui mal atendida na recepção e o serviço atrasou duas horas.', 'Ana, pedimos desculpas pelo transtorno. Já alinhamos o atendimento da recepção.', DATE_SUB(NOW(), INTERVAL 43 DAY), DATE_SUB(NOW(), INTERVAL 44 DAY));

-- n4: bruno em oficina 2 (freio), há 100 dias
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@bruno, @o2, @s_freio, @v2, DATE_SUB(NOW(), INTERVAL 100 DAY), 'Pedal do freio ficando baixo.', 'concluido', 'Confirmado, pode vir no horário.', DATE_SUB(NOW(), INTERVAL 102 DAY), 265.00, DATE_SUB(NOW(), INTERVAL 103 DAY));
SET @a_n4 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a_n4, @o2, 'Edson', 86500, 'entregue', 'Pastilhas no limite e fluido contaminado.', 'Discos em bom estado; checar novamente em 20 mil km.', 90.00, NULL, DATE_SUB(NOW(), INTERVAL 100 DAY), DATE_SUB(NOW(), INTERVAL 100 DAY));
SET @os_n4 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os_n4, 'Jogo de pastilhas dianteiras', 1.00, 140.00),
 (@os_n4, 'Fluido de freio DOT4 (500 ml)', 1.00, 35.00);
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v2, @o2, @s_freio, DATE_SUB(CURDATE(), INTERVAL 100 DAY), 86500, 265.00, 175.00, 90.00);
SET @h_n4 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h_n4, 'Jogo de pastilhas dianteiras', 1.00, 140.00),
 (@h_n4, 'Fluido de freio DOT4 (500 ml)', 1.00, 35.00);
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, resposta_oficina, data_resposta, data_avaliacao) VALUES
 (@bruno, @o2, @a_n4, 5, 'Freio ficou perfeito e explicaram o desgaste dos discos com clareza.', 'Valeu, Bruno! Segurança em primeiro lugar.', DATE_SUB(NOW(), INTERVAL 98 DAY), DATE_SUB(NOW(), INTERVAL 99 DAY));

-- n5: bruno em oficina 4 (ar), há 70 dias
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@bruno, @o4, @s_ar, @v3, DATE_SUB(NOW(), INTERVAL 70 DAY), 'Ar-condicionado fraco.', 'concluido', 'Confirmado, pode vir no horário.', DATE_SUB(NOW(), INTERVAL 72 DAY), 260.00, DATE_SUB(NOW(), INTERVAL 73 DAY));
SET @a_n5 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a_n5, @o4, 'Jonas', 29500, 'entregue', 'Baixa carga de gás e filtro de cabine saturado.', NULL, 130.00, NULL, DATE_SUB(NOW(), INTERVAL 70 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY));
SET @os_n5 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os_n5, 'Gás refrigerante R134a', 1.00, 90.00),
 (@os_n5, 'Filtro de cabine', 1.00, 40.00);
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v3, @o4, @s_ar, DATE_SUB(CURDATE(), INTERVAL 70 DAY), 29500, 260.00, 130.00, 130.00);
SET @h_n5 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h_n5, 'Gás refrigerante R134a', 1.00, 90.00),
 (@h_n5, 'Filtro de cabine', 1.00, 40.00);
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, data_avaliacao) VALUES
 (@bruno, @o4, @a_n5, 4, 'O ar voltou a gelar. Local simples, mas serviço honesto.', DATE_SUB(NOW(), INTERVAL 69 DAY));

-- n6: bruno em oficina 1 (alin), há 35 dias
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@bruno, @o1, @s_alin, @v3, DATE_SUB(NOW(), INTERVAL 35 DAY), 'Alinhamento e balanceamento.', 'concluido', 'Confirmado, pode vir no horário.', DATE_SUB(NOW(), INTERVAL 37 DAY), 120.00, DATE_SUB(NOW(), INTERVAL 38 DAY));
SET @a_n6 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a_n6, @o1, 'Marcos (mecânico)', 30500, 'entregue', 'Alinhamento levemente fora; balanceamento ok.', NULL, 120.00, NULL, DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY));
SET @os_n6 = LAST_INSERT_ID();
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v3, @o1, @s_alin, DATE_SUB(CURDATE(), INTERVAL 35 DAY), 30500, 120.00, 0.00, 120.00);
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, resposta_oficina, data_resposta, data_avaliacao) VALUES
 (@bruno, @o1, @a_n6, 5, 'Rápido: em uma hora estava pronto.', 'Obrigado, Bruno!', DATE_SUB(NOW(), INTERVAL 33 DAY), DATE_SUB(NOW(), INTERVAL 34 DAY));

-- n7: bruno em oficina 5 (oleo), há 15 dias
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@bruno, @o5, @s_oleo, @v2, DATE_SUB(NOW(), INTERVAL 15 DAY), 'Troca de óleo antes de viagem.', 'concluido', 'Confirmado, pode vir no horário.', DATE_SUB(NOW(), INTERVAL 17 DAY), 215.00, DATE_SUB(NOW(), INTERVAL 18 DAY));
SET @a_n7 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a_n7, @o5, 'Rafael', 91000, 'entregue', 'Nível de óleo baixo.', NULL, 60.00, NULL, DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY));
SET @os_n7 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os_n7, 'Óleo 5W30 sintético (litro)', 4.00, 30.00),
 (@os_n7, 'Filtro de óleo', 1.00, 35.00);
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v2, @o5, @s_oleo, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 91000, 215.00, 155.00, 60.00);
SET @h_n7 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h_n7, 'Óleo 5W30 sintético (litro)', 4.00, 30.00),
 (@h_n7, 'Filtro de óleo', 1.00, 35.00);
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, resposta_oficina, data_resposta, data_avaliacao) VALUES
 (@bruno, @o5, @a_n7, 3, 'Serviço ok, mas o preço do óleo estava acima do que vi no app.', 'Bruno, o óleo aplicado é o recomendado pela montadora. Agradecemos o feedback.', DATE_SUB(NOW(), INTERVAL 13 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY));

-- n8: carla em oficina 2 (alin), há 100 dias
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@carla, @o2, @s_alin, @v4, DATE_SUB(NOW(), INTERVAL 100 DAY), 'Carro vibrando em velocidade de estrada.', 'concluido', 'Confirmado, pode vir no horário.', DATE_SUB(NOW(), INTERVAL 102 DAY), 110.00, DATE_SUB(NOW(), INTERVAL 103 DAY));
SET @a_n8 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a_n8, @o2, 'Edson', 12000, 'entregue', 'Rodas desbalanceadas.', 'Cliente orientada sobre calibragem.', 110.00, NULL, DATE_SUB(NOW(), INTERVAL 100 DAY), DATE_SUB(NOW(), INTERVAL 100 DAY));
SET @os_n8 = LAST_INSERT_ID();
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v4, @o2, @s_alin, DATE_SUB(CURDATE(), INTERVAL 100 DAY), 12000, 110.00, 0.00, 110.00);
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, resposta_oficina, data_resposta, data_avaliacao) VALUES
 (@carla, @o2, @a_n8, 5, 'Atendimento atencioso, explicaram o que era cambagem e convergência.', 'Muito obrigado, Carla!', DATE_SUB(NOW(), INTERVAL 98 DAY), DATE_SUB(NOW(), INTERVAL 99 DAY));

-- n12: carla em oficina 1 (oleo), há 55 dias
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@carla, @o1, @s_oleo, @v4, DATE_SUB(NOW(), INTERVAL 55 DAY), 'Primeira troca de óleo do carro.', 'concluido', 'Confirmado, pode vir no horário.', DATE_SUB(NOW(), INTERVAL 57 DAY), 194.00, DATE_SUB(NOW(), INTERVAL 58 DAY));
SET @a_n12 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a_n12, @o1, 'Marcos (mecânico)', 14500, 'entregue', 'Troca preventiva.', NULL, 60.00, NULL, DATE_SUB(NOW(), INTERVAL 55 DAY), DATE_SUB(NOW(), INTERVAL 55 DAY));
SET @os_n12 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os_n12, 'Óleo 5W30 semissintético (litro)', 4.00, 26.00),
 (@os_n12, 'Filtro de óleo', 1.00, 30.00);
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v4, @o1, @s_oleo, DATE_SUB(CURDATE(), INTERVAL 55 DAY), 14500, 194.00, 134.00, 60.00);
SET @h_n12 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h_n12, 'Óleo 5W30 semissintético (litro)', 4.00, 26.00),
 (@h_n12, 'Filtro de óleo', 1.00, 30.00);
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, data_avaliacao) VALUES
 (@carla, @o1, @a_n12, 4, 'Tudo certo, mas a fila de espera na recepção foi longa.', DATE_SUB(NOW(), INTERVAL 54 DAY));

-- n9: carla em oficina 3 (oleo), há 35 dias
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@carla, @o3, @s_oleo, @v4, DATE_SUB(NOW(), INTERVAL 35 DAY), 'Troca de óleo.', 'concluido', 'Confirmado, pode vir no horário.', DATE_SUB(NOW(), INTERVAL 37 DAY), 166.00, DATE_SUB(NOW(), INTERVAL 38 DAY));
SET @a_n9 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a_n9, @o3, 'Seu Antônio', 15000, 'entregue', 'Óleo dentro do prazo, troca preventiva.', NULL, 40.00, NULL, DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY));
SET @os_n9 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os_n9, 'Óleo 5W30 semissintético (litro)', 4.00, 24.00),
 (@os_n9, 'Filtro de óleo', 1.00, 30.00);
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v4, @o3, @s_oleo, DATE_SUB(CURDATE(), INTERVAL 35 DAY), 15000, 166.00, 126.00, 40.00);
SET @h_n9 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h_n9, 'Óleo 5W30 semissintético (litro)', 4.00, 24.00),
 (@h_n9, 'Filtro de óleo', 1.00, 30.00);
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, data_avaliacao) VALUES
 (@carla, @o3, @a_n9, 4, 'Bom preço e atendimento rápido.', DATE_SUB(NOW(), INTERVAL 34 DAY));

-- n10: carla em oficina 2 (freio), há 8 dias
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@carla, @o2, @s_freio, @v4, DATE_SUB(NOW(), INTERVAL 8 DAY), 'Pastilhas dianteiras gastas.', 'concluido', 'Confirmado, pode vir no horário.', DATE_SUB(NOW(), INTERVAL 10 DAY), 205.00, DATE_SUB(NOW(), INTERVAL 11 DAY));
SET @a_n10 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, desconto, criado_em, concluida_em) VALUES
 (@a_n10, @o2, 'Edson', 15800, 'entregue', 'Pastilhas no limite do desgaste.', 'Desconto aplicado pela promoção de teste de freio.', 90.00, 10.00, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY));
SET @os_n10 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os_n10, 'Jogo de pastilhas dianteiras', 1.00, 125.00);
INSERT INTO historico_manutencoes (id_veiculo, id_oficina, id_servico, data_servico, km_servico, valor_total, valor_pecas, valor_mao_obra) VALUES
 (@v4, @o2, @s_freio, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 15800, 205.00, 125.00, 90.00);
SET @h_n10 = LAST_INSERT_ID();
INSERT INTO pecas_utilizadas (id_historico, nome, quantidade, valor_unitario) VALUES
 (@h_n10, 'Jogo de pastilhas dianteiras', 1.00, 125.00);
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, resposta_oficina, data_resposta, data_avaliacao) VALUES
 (@carla, @o2, @a_n10, 5, 'Pastilhas trocadas no mesmo dia. Recomendo!', 'Obrigado, Carla!', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY));

-- Avaliações sem serviço vinculado (sem o selo "serviço confirmado")
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, resposta_oficina, data_resposta, data_avaliacao) VALUES
 (@carla, @o2, NULL, 5, 'Perguntei o preço pelo chat e responderam em minutos, com todos os detalhes.', 'Obrigado, Carla! Estamos à disposição.', DATE_SUB(NOW(), INTERVAL 26 DAY), DATE_SUB(NOW(), INTERVAL 27 DAY));
INSERT INTO avaliacoes (id_usuario, id_oficina, id_agendamento, nota, comentario, data_avaliacao) VALUES
 (@ana, @o5, NULL, 4, 'Passei para pedir orçamento e fui bem recebida. Instalações organizadas.', DATE_SUB(NOW(), INTERVAL 40 DAY)),
 (@bruno, @o4, NULL, 2, 'Mandei mensagem pelo chat e não tive retorno em dois dias.', DATE_SUB(NOW(), INTERVAL 22 DAY));

-- Ordens de serviço em andamento (aberta / em execução / pronta)
INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@ana, @o2, @s_susp, @v1, DATE_SUB(NOW(), INTERVAL 2 HOUR), 'Barulho ao passar em lombadas.', 'aceito', 'Aceito. Pode trazer o veículo.', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, DATE_SUB(NOW(), INTERVAL 3 DAY));
SET @a_x1 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, criado_em) VALUES
 (@a_x1, @o2, 'Edson', 48900, 'aberta', NULL, 'Aguardando avaliação inicial na bancada.', NULL, DATE_SUB(NOW(), INTERVAL 2 HOUR));
SET @os_x1 = LAST_INSERT_ID();

INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@carla, @o3, @s_ele, @v4, DATE_SUB(NOW(), INTERVAL 4 HOUR), 'Farol baixo esquerdo não acende.', 'aceito', 'Aceito. Pode trazer o veículo.', DATE_SUB(NOW(), INTERVAL 2 DAY), 165.00, DATE_SUB(NOW(), INTERVAL 3 DAY));
SET @a_x2 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, criado_em) VALUES
 (@a_x2, @o3, 'Seu Antônio', 15900, 'em_execucao', 'Soquete derretido e mau contato no chicote.', 'Troca do soquete e das duas lâmpadas.', 80.00, DATE_SUB(NOW(), INTERVAL 4 HOUR));
SET @os_x2 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os_x2, 'Soquete de lâmpada H7', 1.00, 25.00),
 (@os_x2, 'Lâmpada H7', 2.00, 30.00);

INSERT INTO agendamentos (id_usuario, id_oficina, id_servico, id_veiculo, data_hora_solicitada, mensagem_usuario, status, resposta_oficina, respondido_em, valor_total, criado_em) VALUES
 (@bruno, @o1, @s_rev, @v3, DATE_SUB(NOW(), INTERVAL 6 HOUR), 'Revisão dos 30 mil km.', 'aceito', 'Aceito. Pode trazer o veículo.', DATE_SUB(NOW(), INTERVAL 2 DAY), 432.00, DATE_SUB(NOW(), INTERVAL 3 DAY));
SET @a_x3 = LAST_INSERT_ID();
INSERT INTO ordens_servico (id_agendamento, id_oficina, responsavel, km_entrada, etapa, diagnostico, observacoes, valor_mao_obra, criado_em) VALUES
 (@a_x3, @o1, 'Marcos (mecânico)', 30900, 'pronta', 'Itens de revisão em conformidade; pastilhas com 40% de vida útil.', 'Veículo pronto para retirada.', 200.00, DATE_SUB(NOW(), INTERVAL 6 HOUR));
SET @os_x3 = LAST_INSERT_ID();
INSERT INTO itens_os (id_os, nome, quantidade, valor_unitario) VALUES
 (@os_x3, 'Óleo 5W30 sintético (litro)', 4.00, 28.00),
 (@os_x3, 'Filtro de óleo', 1.00, 35.00),
 (@os_x3, 'Filtro de ar', 1.00, 45.00),
 (@os_x3, 'Filtro de cabine', 1.00, 40.00);

-- Chats: conversas e mensagens (pares usuário × oficina ainda inexistentes)
INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@ana, @o2, DATE_SUB(NOW(), INTERVAL 4320 MINUTE), DATE_SUB(NOW(), INTERVAL 4280 MINUTE));
SET @cx1 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@cx1, 'usuario', 'Oi! Vocês fazem alinhamento com laudo impresso?', DATE_SUB(NOW(), INTERVAL 4320 MINUTE), DATE_SUB(NOW(), INTERVAL 4315 MINUTE)),
 (@cx1, 'oficina', 'Olá, Ana! Fazemos sim, o laudo antes e depois já está incluso.', DATE_SUB(NOW(), INTERVAL 4300 MINUTE), DATE_SUB(NOW(), INTERVAL 4295 MINUTE)),
 (@cx1, 'usuario', 'Ótimo, posso ir amanhã?', DATE_SUB(NOW(), INTERVAL 4290 MINUTE), DATE_SUB(NOW(), INTERVAL 4285 MINUTE)),
 (@cx1, 'oficina', 'Pode. Horário livre às 10h ou às 15h.', DATE_SUB(NOW(), INTERVAL 4280 MINUTE), DATE_SUB(NOW(), INTERVAL 4275 MINUTE));
INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@ana, @o3, DATE_SUB(NOW(), INTERVAL 1500 MINUTE), DATE_SUB(NOW(), INTERVAL 20 MINUTE));
SET @cx2 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@cx2, 'usuario', 'Boa tarde, o vidro do motorista parou de subir.', DATE_SUB(NOW(), INTERVAL 1500 MINUTE), DATE_SUB(NOW(), INTERVAL 1495 MINUTE)),
 (@cx2, 'oficina', 'Boa tarde! Pode trazer o carro amanhã cedo que verificamos o motor e o chicote.', DATE_SUB(NOW(), INTERVAL 1480 MINUTE), DATE_SUB(NOW(), INTERVAL 1475 MINUTE)),
 (@cx2, 'usuario', 'Combinado, obrigada!', DATE_SUB(NOW(), INTERVAL 1470 MINUTE), DATE_SUB(NOW(), INTERVAL 1465 MINUTE)),
 (@cx2, 'oficina', 'Ana, seu carro está pronto para retirada. Ficamos até às 18h.', DATE_SUB(NOW(), INTERVAL 20 MINUTE), NULL);
INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@ana, @o4, DATE_SUB(NOW(), INTERVAL 14400 MINUTE), DATE_SUB(NOW(), INTERVAL 14000 MINUTE));
SET @cx3 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@cx3, 'usuario', 'Vocês fazem higienização do ar-condicionado?', DATE_SUB(NOW(), INTERVAL 14400 MINUTE), DATE_SUB(NOW(), INTERVAL 14395 MINUTE)),
 (@cx3, 'oficina', 'Fazemos. Higienização + recarga a partir de R$ 250.', DATE_SUB(NOW(), INTERVAL 14380 MINUTE), DATE_SUB(NOW(), INTERVAL 14375 MINUTE)),
 (@cx3, 'usuario', 'Vou pensar e retorno, obrigada.', DATE_SUB(NOW(), INTERVAL 14000 MINUTE), DATE_SUB(NOW(), INTERVAL 13995 MINUTE));
INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@ana, @o5, DATE_SUB(NOW(), INTERVAL 3000 MINUTE), DATE_SUB(NOW(), INTERVAL 2870 MINUTE));
SET @cx4 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@cx4, 'usuario', 'Gostaria de reclamar do atraso do último serviço.', DATE_SUB(NOW(), INTERVAL 3000 MINUTE), DATE_SUB(NOW(), INTERVAL 2995 MINUTE)),
 (@cx4, 'oficina', 'Ana, sentimos muito. Podemos conversar sobre como compensar na próxima visita?', DATE_SUB(NOW(), INTERVAL 2900 MINUTE), DATE_SUB(NOW(), INTERVAL 2895 MINUTE)),
 (@cx4, 'usuario', 'Pode ser, vou avaliar.', DATE_SUB(NOW(), INTERVAL 2880 MINUTE), DATE_SUB(NOW(), INTERVAL 2875 MINUTE)),
 (@cx4, 'oficina', 'Fique à vontade. Seu feedback já foi repassado à equipe.', DATE_SUB(NOW(), INTERVAL 2870 MINUTE), DATE_SUB(NOW(), INTERVAL 2865 MINUTE));
INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@bruno, @o1, DATE_SUB(NOW(), INTERVAL 600 MINUTE), DATE_SUB(NOW(), INTERVAL 560 MINUTE));
SET @cx5 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@cx5, 'usuario', 'Boa tarde! Qual o prazo da revisão de 30 mil km?', DATE_SUB(NOW(), INTERVAL 600 MINUTE), DATE_SUB(NOW(), INTERVAL 595 MINUTE)),
 (@cx5, 'oficina', 'Boa tarde, Bruno. Em geral 1 dia útil. Seu carro já está pronto!', DATE_SUB(NOW(), INTERVAL 580 MINUTE), DATE_SUB(NOW(), INTERVAL 575 MINUTE)),
 (@cx5, 'usuario', 'Perfeito, passo às 17h para retirar.', DATE_SUB(NOW(), INTERVAL 570 MINUTE), DATE_SUB(NOW(), INTERVAL 565 MINUTE)),
 (@cx5, 'oficina', 'Combinado. Estaremos aguardando.', DATE_SUB(NOW(), INTERVAL 560 MINUTE), DATE_SUB(NOW(), INTERVAL 555 MINUTE));
INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@bruno, @o3, DATE_SUB(NOW(), INTERVAL 380 MINUTE), DATE_SUB(NOW(), INTERVAL 360 MINUTE));
SET @cx6 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@cx6, 'usuario', 'Qual a previsão da peça do alternador?', DATE_SUB(NOW(), INTERVAL 380 MINUTE), DATE_SUB(NOW(), INTERVAL 375 MINUTE)),
 (@cx6, 'oficina', 'Chega amanhã cedo. Assim que instalar, aviso por aqui.', DATE_SUB(NOW(), INTERVAL 370 MINUTE), DATE_SUB(NOW(), INTERVAL 365 MINUTE)),
 (@cx6, 'usuario', 'Perfeito, obrigado!', DATE_SUB(NOW(), INTERVAL 360 MINUTE), DATE_SUB(NOW(), INTERVAL 355 MINUTE));
INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@bruno, @o4, DATE_SUB(NOW(), INTERVAL 4100 MINUTE), DATE_SUB(NOW(), INTERVAL 2900 MINUTE));
SET @cx7 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@cx7, 'usuario', 'Alguém por aí? Preciso de retorno sobre o ar-condicionado.', DATE_SUB(NOW(), INTERVAL 4100 MINUTE), DATE_SUB(NOW(), INTERVAL 4095 MINUTE)),
 (@cx7, 'usuario', 'Enviei mensagem há dois dias e nada.', DATE_SUB(NOW(), INTERVAL 2900 MINUTE), NULL);
INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@bruno, @o5, DATE_SUB(NOW(), INTERVAL 700 MINUTE), DATE_SUB(NOW(), INTERVAL 640 MINUTE));
SET @cx8 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@cx8, 'usuario', 'Boa tarde, a recomendação de pneus novos vale para quando?', DATE_SUB(NOW(), INTERVAL 700 MINUTE), DATE_SUB(NOW(), INTERVAL 695 MINUTE)),
 (@cx8, 'oficina', 'Bruno, em torno de 10 mil km, mas dá para checar na próxima visita.', DATE_SUB(NOW(), INTERVAL 650 MINUTE), DATE_SUB(NOW(), INTERVAL 645 MINUTE)),
 (@cx8, 'usuario', 'Valeu, vou me programar.', DATE_SUB(NOW(), INTERVAL 640 MINUTE), DATE_SUB(NOW(), INTERVAL 635 MINUTE));
INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@carla, @o1, DATE_SUB(NOW(), INTERVAL 7200 MINUTE), DATE_SUB(NOW(), INTERVAL 7165 MINUTE));
SET @cx9 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@cx9, 'usuario', 'Preciso de alinhamento, tem horário amanhã?', DATE_SUB(NOW(), INTERVAL 7200 MINUTE), DATE_SUB(NOW(), INTERVAL 7195 MINUTE)),
 (@cx9, 'oficina', 'Temos às 9h30 e 14h.', DATE_SUB(NOW(), INTERVAL 7180 MINUTE), DATE_SUB(NOW(), INTERVAL 7175 MINUTE)),
 (@cx9, 'usuario', 'Prefiro 14h.', DATE_SUB(NOW(), INTERVAL 7170 MINUTE), DATE_SUB(NOW(), INTERVAL 7165 MINUTE)),
 (@cx9, 'oficina', 'Reservado, Carla. Até lá!', DATE_SUB(NOW(), INTERVAL 7165 MINUTE), DATE_SUB(NOW(), INTERVAL 7160 MINUTE));
INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@carla, @o2, DATE_SUB(NOW(), INTERVAL 12000 MINUTE), DATE_SUB(NOW(), INTERVAL 11900 MINUTE));
SET @cx10 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@cx10, 'usuario', 'Quanto fica a troca de pastilhas dianteiras?', DATE_SUB(NOW(), INTERVAL 12000 MINUTE), DATE_SUB(NOW(), INTERVAL 11995 MINUTE)),
 (@cx10, 'oficina', 'Com mão de obra, R$ 215. Estamos com teste grátis de fluido de freio.', DATE_SUB(NOW(), INTERVAL 11950 MINUTE), DATE_SUB(NOW(), INTERVAL 11945 MINUTE)),
 (@cx10, 'usuario', 'Fechado, agendo pelo site.', DATE_SUB(NOW(), INTERVAL 11900 MINUTE), DATE_SUB(NOW(), INTERVAL 11895 MINUTE));
INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@carla, @o3, DATE_SUB(NOW(), INTERVAL 2000 MINUTE), DATE_SUB(NOW(), INTERVAL 1985 MINUTE));
SET @cx11 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@cx11, 'usuario', 'Vocês trabalham com óleo semissintético?', DATE_SUB(NOW(), INTERVAL 2000 MINUTE), DATE_SUB(NOW(), INTERVAL 1995 MINUTE)),
 (@cx11, 'oficina', 'Trabalhamos sim, Carla. Troca completa com filtro por R$ 144.', DATE_SUB(NOW(), INTERVAL 1990 MINUTE), DATE_SUB(NOW(), INTERVAL 1985 MINUTE)),
 (@cx11, 'usuario', 'Obrigada!', DATE_SUB(NOW(), INTERVAL 1985 MINUTE), DATE_SUB(NOW(), INTERVAL 1980 MINUTE));
INSERT INTO conversas (id_usuario, id_oficina, criado_em, ultima_mensagem_em) VALUES
 (@carla, @o4, DATE_SUB(NOW(), INTERVAL 1000 MINUTE), DATE_SUB(NOW(), INTERVAL 910 MINUTE));
SET @cx12 = LAST_INSERT_ID();
INSERT INTO mensagens (id_conversa, remetente, texto, criado_em, lida_em) VALUES
 (@cx12, 'usuario', 'Gostaria de entender a diferença cobrada na última troca de óleo.', DATE_SUB(NOW(), INTERVAL 1000 MINUTE), DATE_SUB(NOW(), INTERVAL 995 MINUTE)),
 (@cx12, 'oficina', 'Carla, o filtro de óleo não constava no orçamento inicial. Podemos enviar o detalhamento.', DATE_SUB(NOW(), INTERVAL 930 MINUTE), DATE_SUB(NOW(), INTERVAL 925 MINUTE)),
 (@cx12, 'usuario', 'Ok, pode enviar por favor.', DATE_SUB(NOW(), INTERVAL 910 MINUTE), NULL);

SET SQL_SAFE_UPDATES = 1;

-- ---------- Conferência: linhas por tabela ----------
SELECT 'usuarios' t, COUNT(*) n FROM usuarios UNION ALL SELECT 'oficinas', COUNT(*) FROM oficinas
UNION ALL SELECT 'servicos', COUNT(*) FROM servicos UNION ALL SELECT 'oficina_servico', COUNT(*) FROM oficina_servico
UNION ALL SELECT 'promocoes', COUNT(*) FROM promocoes UNION ALL SELECT 'veiculos', COUNT(*) FROM veiculos
UNION ALL SELECT 'historico_manutencoes', COUNT(*) FROM historico_manutencoes UNION ALL SELECT 'pecas_utilizadas', COUNT(*) FROM pecas_utilizadas
UNION ALL SELECT 'pedidos_orcamento', COUNT(*) FROM pedidos_orcamento UNION ALL SELECT 'orcamentos', COUNT(*) FROM orcamentos
UNION ALL SELECT 'anexos_pedido', COUNT(*) FROM anexos_pedido UNION ALL SELECT 'agendamentos', COUNT(*) FROM agendamentos
UNION ALL SELECT 'ordens_servico', COUNT(*) FROM ordens_servico UNION ALL SELECT 'itens_os', COUNT(*) FROM itens_os
UNION ALL SELECT 'avaliacoes', COUNT(*) FROM avaliacoes UNION ALL SELECT 'denuncias', COUNT(*) FROM denuncias
UNION ALL SELECT 'favoritos', COUNT(*) FROM favoritos UNION ALL SELECT 'conversas', COUNT(*) FROM conversas
UNION ALL SELECT 'mensagens', COUNT(*) FROM mensagens UNION ALL SELECT 'tentativas_login', COUNT(*) FROM tentativas_login
UNION ALL SELECT 'tokens_verificacao_email', COUNT(*) FROM tokens_verificacao_email
UNION ALL SELECT 'log_auditoria', COUNT(*) FROM log_auditoria UNION ALL SELECT 'log_auditoria_detalhe', COUNT(*) FROM log_auditoria_detalhe;

-- ---------------------------------------------------------------------------
-- Placa: copia para agendamentos.placa_snapshot (usada pela oficina) e,
-- depois, rode  php backend/tools/cifrar_placas.php  para cifrar as placas.
-- ---------------------------------------------------------------------------
SET SQL_SAFE_UPDATES = 0;
UPDATE agendamentos a JOIN veiculos v ON v.id_veiculo = a.id_veiculo
   SET a.placa_snapshot = v.placa WHERE a.placa_snapshot IS NULL;
