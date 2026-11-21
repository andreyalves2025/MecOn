-- MecOn - Schema canônico do banco de dados
-- Consolida o DDL original (PI_1_3) + migrações 001 a 028.
-- Ordem de criação corrigida: `veiculos` agora vem antes de
-- `historico_manutencoes`, que depende dela via FOREIGN KEY.

CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `email_verificado` tinyint(1) NOT NULL DEFAULT 0,
  `termos_aceitos_em` timestamp NULL DEFAULT NULL,
  `termos_versao` varchar(20) DEFAULT NULL,
  `tipo` enum('cliente','admin') NOT NULL DEFAULT 'cliente',
  `data_cadastro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `oficinas` (
  `id_oficina` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL DEFAULT '',
  `senha_hash` varchar(255) NOT NULL DEFAULT '',
  `cidade` varchar(100) NOT NULL,
  `bairro` varchar(100) NOT NULL,
  `endereco` varchar(255) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `telefone` varchar(20) NOT NULL,
  `descricao` text,
  `verificada` tinyint(1) NOT NULL DEFAULT 0,
  `verificada_em` timestamp NULL DEFAULT NULL,
  `ativa` tinyint(1) NOT NULL DEFAULT 1,
  `desativada_em` timestamp NULL DEFAULT NULL,
  `data_cadastro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_oficina`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `servicos` (
  `id_servico` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `intervalo_recomendado_meses` int DEFAULT NULL,
  `intervalo_recomendado_km` int DEFAULT NULL,
  PRIMARY KEY (`id_servico`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `oficina_servico` (
  `id_oficina` int NOT NULL,
  `id_servico` int NOT NULL,
  `preco` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_oficina`,`id_servico`),
  KEY `oficina_servico_ibfk_2` (`id_servico`),
  CONSTRAINT `oficina_servico_ibfk_1` FOREIGN KEY (`id_oficina`) REFERENCES `oficinas` (`id_oficina`) ON DELETE CASCADE,
  CONSTRAINT `oficina_servico_ibfk_2` FOREIGN KEY (`id_servico`) REFERENCES `servicos` (`id_servico`) ON DELETE CASCADE,
  CONSTRAINT `chk_preco_servico` CHECK ((`preco` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `promocoes` (
  `id_promocao` int NOT NULL AUTO_INCREMENT,
  `id_oficina` int NOT NULL,
  `id_servico` int DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `descricao` text,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_promocao`),
  KEY `promocoes_ibfk_1` (`id_oficina`),
  KEY `promocoes_ibfk_2` (`id_servico`),
  CONSTRAINT `promocoes_ibfk_1` FOREIGN KEY (`id_oficina`) REFERENCES `oficinas` (`id_oficina`) ON DELETE CASCADE,
  CONSTRAINT `promocoes_ibfk_2` FOREIGN KEY (`id_servico`) REFERENCES `servicos` (`id_servico`) ON DELETE SET NULL,
  CONSTRAINT `chk_periodo_promocao` CHECK ((`data_fim` >= `data_inicio`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `avaliacoes` (
  `id_avaliacao` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NULL,
  `id_oficina` int NOT NULL,
  `id_agendamento` int NULL,
  `nota` int NOT NULL,
  `comentario` text,
  `resposta_oficina` text,
  `data_resposta` timestamp NULL DEFAULT NULL,
  `data_avaliacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_avaliacao`),
  KEY `avaliacoes_ibfk_1` (`id_usuario`),
  KEY `avaliacoes_ibfk_2` (`id_oficina`),
  UNIQUE KEY `avaliacao_por_agendamento` (`id_agendamento`),
  CONSTRAINT `avaliacoes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `avaliacoes_ibfk_2` FOREIGN KEY (`id_oficina`) REFERENCES `oficinas` (`id_oficina`) ON DELETE CASCADE,
  CONSTRAINT `chk_nota` CHECK ((`nota` between 1 and 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
-- A FOREIGN KEY de id_agendamento é adicionada mais abaixo, via ALTER TABLE,
-- porque a tabela `agendamentos` só é criada depois de `avaliacoes` neste
-- script (evita o mesmo erro de ordem já corrigido antes em
-- historico_manutencoes/veiculos).

CREATE TABLE `denuncias` (
  `id_denuncia` int NOT NULL AUTO_INCREMENT,
  `id_avaliacao` int NOT NULL,
  `id_usuario` int NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `status` enum('pendente','descartada') NOT NULL DEFAULT 'pendente',
  `data_denuncia` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_denuncia`),
  UNIQUE KEY `denuncia_unica` (`id_avaliacao`, `id_usuario`),
  KEY `denuncias_ibfk_2` (`id_usuario`),
  CONSTRAINT `denuncias_ibfk_1` FOREIGN KEY (`id_avaliacao`) REFERENCES `avaliacoes` (`id_avaliacao`) ON DELETE CASCADE,
  CONSTRAINT `denuncias_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `favoritos` (
  `id_usuario` int NOT NULL,
  `id_oficina` int NOT NULL,
  PRIMARY KEY (`id_usuario`,`id_oficina`),
  KEY `favoritos_ibfk_2` (`id_oficina`),
  CONSTRAINT `favoritos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `favoritos_ibfk_2` FOREIGN KEY (`id_oficina`) REFERENCES `oficinas` (`id_oficina`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- `veiculos` precisa existir antes de `historico_manutencoes` (que a referencia)
CREATE TABLE `veiculos` (
  `id_veiculo` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `placa` varchar(255) NOT NULL,
  `km_atual` int DEFAULT NULL,
  `data_cadastro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_veiculo`),
  KEY `veiculos_ibfk_1` (`id_usuario`),
  CONSTRAINT `veiculos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `historico_manutencoes` (
  `id_historico` int NOT NULL AUTO_INCREMENT,
  `id_veiculo` int NOT NULL,
  `id_oficina` int NOT NULL,
  `id_servico` int NOT NULL,
  `data_servico` date NOT NULL,
  `km_servico` int DEFAULT NULL,
  `valor_total` decimal(10,2) DEFAULT NULL,
  `valor_pecas` decimal(10,2) DEFAULT NULL,
  `valor_mao_obra` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id_historico`),
  KEY `historico_manutencoes_ibfk_2` (`id_oficina`),
  KEY `historico_manutencoes_ibfk_3` (`id_servico`),
  KEY `historico_manutencoes_ibfk_1` (`id_veiculo`),
  CONSTRAINT `historico_manutencoes_ibfk_1` FOREIGN KEY (`id_veiculo`) REFERENCES `veiculos` (`id_veiculo`) ON DELETE CASCADE,
  CONSTRAINT `historico_manutencoes_ibfk_2` FOREIGN KEY (`id_oficina`) REFERENCES `oficinas` (`id_oficina`) ON DELETE RESTRICT,
  CONSTRAINT `historico_manutencoes_ibfk_3` FOREIGN KEY (`id_servico`) REFERENCES `servicos` (`id_servico`) ON DELETE RESTRICT,
  CONSTRAINT `chk_historico_valores` CHECK (((`valor_total` IS NULL) OR (`valor_total` >= 0)) AND ((`valor_pecas` IS NULL) OR (`valor_pecas` >= 0)) AND ((`valor_mao_obra` IS NULL) OR (`valor_mao_obra` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pecas_utilizadas` (
  `id_peca` int NOT NULL AUTO_INCREMENT,
  `id_historico` int NOT NULL,
  `nome` varchar(150) NOT NULL,
  `quantidade` decimal(8,2) NOT NULL DEFAULT 1.00,
  `valor_unitario` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id_peca`),
  KEY `pecas_utilizadas_ibfk_1` (`id_historico`),
  CONSTRAINT `pecas_utilizadas_ibfk_1` FOREIGN KEY (`id_historico`) REFERENCES `historico_manutencoes` (`id_historico`) ON DELETE CASCADE,
  CONSTRAINT `chk_peca_quantidade` CHECK (`quantidade` > 0),
  CONSTRAINT `chk_peca_valor` CHECK (`valor_unitario` IS NULL OR `valor_unitario` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `pedidos_orcamento` (
  `id_pedido` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_veiculo` int NOT NULL,
  `id_servico` int DEFAULT NULL,
  `descricao` text NOT NULL,
  `status` enum('aberto','aprovado','cancelado') NOT NULL DEFAULT 'aberto',
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pedido`),
  KEY `pedidos_orcamento_ibfk_1` (`id_usuario`),
  KEY `pedidos_orcamento_ibfk_2` (`id_veiculo`),
  KEY `pedidos_orcamento_ibfk_3` (`id_servico`),
  CONSTRAINT `pedidos_orcamento_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `pedidos_orcamento_ibfk_2` FOREIGN KEY (`id_veiculo`) REFERENCES `veiculos` (`id_veiculo`) ON DELETE CASCADE,
  CONSTRAINT `pedidos_orcamento_ibfk_3` FOREIGN KEY (`id_servico`) REFERENCES `servicos` (`id_servico`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `orcamentos` (
  `id_orcamento` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `id_oficina` int NOT NULL,
  `status` enum('pendente','respondido','aprovado','nao_escolhido','recusado','cancelado') NOT NULL DEFAULT 'pendente',
  `valor_total` decimal(10,2) DEFAULT NULL,
  `valor_pecas` decimal(10,2) DEFAULT NULL,
  `valor_mao_obra` decimal(10,2) DEFAULT NULL,
  `prazo_dias` int DEFAULT NULL,
  `validade_ate` date DEFAULT NULL,
  `detalhes` varchar(1000) DEFAULT NULL,
  `respondido_em` timestamp NULL DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_orcamento`),
  UNIQUE KEY `orcamento_por_oficina` (`id_pedido`, `id_oficina`),
  KEY `orcamentos_ibfk_2` (`id_oficina`),
  CONSTRAINT `orcamentos_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_orcamento` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `orcamentos_ibfk_2` FOREIGN KEY (`id_oficina`) REFERENCES `oficinas` (`id_oficina`) ON DELETE CASCADE,
  CONSTRAINT `chk_orcamento_valores` CHECK (
    (`valor_total` IS NULL OR `valor_total` >= 0)
    AND (`valor_pecas` IS NULL OR `valor_pecas` >= 0)
    AND (`valor_mao_obra` IS NULL OR `valor_mao_obra` >= 0)
    AND (`prazo_dias` IS NULL OR `prazo_dias` >= 0)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `anexos_pedido` (
  `id_anexo` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `nome_original` varchar(255) NOT NULL,
  `nome_arquivo` varchar(64) NOT NULL,
  `tipo_mime` varchar(100) NOT NULL,
  `tamanho_bytes` int NOT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_anexo`),
  UNIQUE KEY `anexo_nome_arquivo` (`nome_arquivo`),
  KEY `anexos_pedido_ibfk_1` (`id_pedido`),
  CONSTRAINT `anexos_pedido_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_orcamento` (`id_pedido`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `agendamentos` (
  `id_agendamento` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NULL,
  `id_oficina` int NOT NULL,
  `id_servico` int NOT NULL,
  `id_veiculo` int NULL,
  `placa_snapshot` varchar(255) NULL,
  `id_orcamento` int NULL,
  `data_hora_solicitada` datetime NOT NULL,
  `mensagem_usuario` varchar(500) DEFAULT NULL,
  `status` enum('pendente','aceito','recusado','cancelado','concluido') NOT NULL DEFAULT 'pendente',
  `resposta_oficina` varchar(500) DEFAULT NULL,
  `respondido_em` timestamp NULL DEFAULT NULL,
  `valor_total` decimal(10,2) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_agendamento`),
  KEY `agendamentos_ibfk_1` (`id_usuario`),
  KEY `agendamentos_ibfk_2` (`id_oficina`),
  KEY `agendamentos_ibfk_3` (`id_servico`),
  KEY `agendamentos_ibfk_4` (`id_veiculo`),
  KEY `agendamentos_ibfk_5` (`id_orcamento`),
  CONSTRAINT `agendamentos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `agendamentos_ibfk_2` FOREIGN KEY (`id_oficina`) REFERENCES `oficinas` (`id_oficina`) ON DELETE RESTRICT,
  CONSTRAINT `agendamentos_ibfk_3` FOREIGN KEY (`id_servico`) REFERENCES `servicos` (`id_servico`) ON DELETE RESTRICT,
  CONSTRAINT `agendamentos_ibfk_4` FOREIGN KEY (`id_veiculo`) REFERENCES `veiculos` (`id_veiculo`) ON DELETE SET NULL,
  CONSTRAINT `agendamentos_ibfk_5` FOREIGN KEY (`id_orcamento`) REFERENCES `orcamentos` (`id_orcamento`) ON DELETE SET NULL,
  CONSTRAINT `chk_agendamento_valor` CHECK ((`valor_total` IS NULL) OR (`valor_total` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `conversas` (
  `id_conversa` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_oficina` int NOT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ultima_mensagem_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_conversa`),
  UNIQUE KEY `conversa_unica` (`id_usuario`, `id_oficina`),
  KEY `conversas_ibfk_2` (`id_oficina`),
  CONSTRAINT `conversas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `conversas_ibfk_2` FOREIGN KEY (`id_oficina`) REFERENCES `oficinas` (`id_oficina`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `mensagens` (
  `id_mensagem` int NOT NULL AUTO_INCREMENT,
  `id_conversa` int NOT NULL,
  `remetente` enum('usuario','oficina') NOT NULL,
  `texto` varchar(2000) NOT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `lida_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_mensagem`),
  KEY `mensagens_conversa_ordem` (`id_conversa`, `id_mensagem`),
  CONSTRAINT `mensagens_ibfk_1` FOREIGN KEY (`id_conversa`) REFERENCES `conversas` (`id_conversa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `ordens_servico` (
  `id_os` int NOT NULL AUTO_INCREMENT,
  `id_agendamento` int NOT NULL,
  `id_oficina` int NOT NULL,
  `responsavel` varchar(100) DEFAULT NULL,
  `km_entrada` int DEFAULT NULL,
  `etapa` enum('aberta','em_execucao','aguardando_peca','pronta','entregue') NOT NULL DEFAULT 'aberta',
  `diagnostico` text,
  `observacoes` varchar(1000) DEFAULT NULL,
  `valor_mao_obra` decimal(10,2) DEFAULT NULL,
  `desconto` decimal(10,2) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `concluida_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_os`),
  UNIQUE KEY `os_por_agendamento` (`id_agendamento`),
  KEY `ordens_servico_ibfk_2` (`id_oficina`),
  CONSTRAINT `ordens_servico_ibfk_1` FOREIGN KEY (`id_agendamento`) REFERENCES `agendamentos` (`id_agendamento`) ON DELETE CASCADE,
  CONSTRAINT `ordens_servico_ibfk_2` FOREIGN KEY (`id_oficina`) REFERENCES `oficinas` (`id_oficina`) ON DELETE RESTRICT,
  CONSTRAINT `chk_os_valores` CHECK (
    (`valor_mao_obra` IS NULL OR `valor_mao_obra` >= 0)
    AND (`desconto` IS NULL OR `desconto` >= 0)
    AND (`km_entrada` IS NULL OR `km_entrada` >= 0)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `itens_os` (
  `id_item` int NOT NULL AUTO_INCREMENT,
  `id_os` int NOT NULL,
  `nome` varchar(150) NOT NULL,
  `quantidade` decimal(8,2) NOT NULL DEFAULT 1.00,
  `valor_unitario` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id_item`),
  KEY `itens_os_ibfk_1` (`id_os`),
  CONSTRAINT `itens_os_ibfk_1` FOREIGN KEY (`id_os`) REFERENCES `ordens_servico` (`id_os`) ON DELETE CASCADE,
  CONSTRAINT `chk_item_os_quantidade` CHECK (`quantidade` > 0),
  CONSTRAINT `chk_item_os_valor` CHECK (`valor_unitario` IS NULL OR `valor_unitario` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `tentativas_login` (
  `email` varchar(100) NOT NULL,
  `tentativas` int NOT NULL DEFAULT 1,
  `atualizado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `bloqueado_ate` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `tokens_verificacao_email` (
  `token` char(64) NOT NULL,
  `id_usuario` int NOT NULL,
  `expira_em` timestamp NOT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token`),
  KEY `tokens_verificacao_email_ibfk_1` (`id_usuario`),
  CONSTRAINT `tokens_verificacao_email_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `log_auditoria` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `tipo_ator` enum('admin','usuario') NOT NULL DEFAULT 'admin',
  `id_ator` int NOT NULL,
  `acao` varchar(100) NOT NULL,
  `entidade` varchar(50) NOT NULL,
  `id_entidade` int DEFAULT NULL,
  `detalhes` varchar(500) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`),
  KEY `log_auditoria_criado_em` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `log_auditoria_detalhe` (
  `id_detalhe` int NOT NULL AUTO_INCREMENT,
  `id_log` int NOT NULL,
  `campo` varchar(100) NOT NULL,
  `valor_anterior` text,
  `valor_novo` text,
  PRIMARY KEY (`id_detalhe`),
  KEY `log_auditoria_detalhe_ibfk_1` (`id_log`),
  CONSTRAINT `log_auditoria_detalhe_ibfk_1` FOREIGN KEY (`id_log`) REFERENCES `log_auditoria` (`id_log`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- FK de avaliacoes.id_agendamento, adicionada aqui porque `agendamentos`
-- só existe a partir deste ponto do script (ver comentário acima, junto
-- à definição de `avaliacoes`).
ALTER TABLE avaliacoes
    ADD CONSTRAINT `avaliacoes_ibfk_3` FOREIGN KEY (`id_agendamento`) REFERENCES `agendamentos` (`id_agendamento`) ON DELETE SET NULL;
