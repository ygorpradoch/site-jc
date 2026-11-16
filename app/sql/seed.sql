-- Conteúdo inicial (importar depois de schema.sql). Pode ser editado depois pelo painel.
SET NAMES utf8mb4;

INSERT INTO condominios (nome, bairro, unidades, desde, foto, exibir) VALUES
('Cond. Praia Nova',        NULL, 92, '2014-06-01', 'assets/img/condominios/praia-nova.webp', 1),
('Cond. Marambaia',         NULL, 92, '2021-06-01', 'assets/img/condominios/marambaia.webp',  1),
('Dom Condomínio Parque',   NULL, 88, '2022-05-01', 'assets/img/condominios/dom-parque.webp', 1),
('Giardino di Fazano',      NULL, 30, '2024-06-01', 'assets/img/condominios/giardino.webp',   1),
('Cond. Abelardo Pompeu',   NULL, 20, '2024-08-01', 'assets/img/condominios/abelardo.webp',   1),
('Ed. Pacific Hills',       NULL, 57, '2025-01-01', NULL, 1);

INSERT INTO artigos (slug, titulo, categoria, minutos, resumo, corpo, status, publicado_em) VALUES
('como-contratar-sindico-profissional',
 'Como contratar um síndico profissional, passo a passo',
 'ELEIÇÃO', 4,
 'Da convocação da assembleia à assinatura do contrato.',
 'O primeiro passo é conferir a convenção. Ela diz como o síndico é eleito, por quanto tempo e se há regras específicas para um síndico de fora do prédio.

Com isso em mãos, o conselho ou um grupo de moradores pede propostas a dois ou três profissionais. Uma boa proposta descreve:

- o escopo do trabalho;
- a frequência de visitas;
- como será a prestação de contas;
- o valor mensal.

## A eleição

A eleição acontece em assembleia convocada com a pauta específica. O mandato pode durar até dois anos, com reeleição permitida (Código Civil, art. 1.347). Depois da eleição, a ata é registrada e começa a transição.',
 'publicado', NOW()),
('fundo-de-reserva',
 'Fundo de reserva: quanto o seu prédio deveria ter guardado',
 'FINANÇAS', 5,
 'O que diz a convenção e como planejar sem aumentar a taxa de uma vez.',
 'O fundo de reserva existe para emergências e obras que não cabem no orçamento do mês. A convenção define o percentual de contribuição, geralmente entre 5% e 10% da taxa condominial.

Uma referência prática é manter guardado o equivalente a algumas taxas mensais de todo o prédio e ter um plano para as obras previstas nos próximos anos, como pintura de fachada e troca de bombas.

## Quando o fundo está baixo

É melhor aprovar uma recomposição gradual em assembleia do que uma taxa extra grande de uma vez.',
 'publicado', NOW()),
('maresia-fortaleza',
 'Maresia em Fortaleza: o que vistoriar no prédio a cada ano',
 'MANUTENÇÃO', 6,
 'Fachada, ferragens, portões e esquadrias perto do mar.',
 'Prédios perto do mar sofrem com a corrosão causada pelo ar salino. Ferragens expostas, portões, grades, esquadrias de alumínio e quadros elétricos são os primeiros a mostrar sinais.

Uma vistoria anual da fachada ajuda a encontrar fissuras antes que a água chegue à armação do concreto. Pintura com tinta adequada e limpeza periódica das esquadrias prolongam a vida útil.

## Registre tudo

Registre tudo no plano de manutenção, com fotos e datas. Isso facilita orçar obras com antecedência e justificar a despesa na assembleia.',
 'publicado', NOW());
