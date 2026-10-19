# Bolinhas e e-mails de aviso (mentoria, vagas, egressos, assinaturas)

Copie para a raiz da Central, mantendo as pastas, aceitando substituir:
  includes/avisos.php        (novo)
  includes/header.php        (bolinhas + sem Empresas Parceiras)
  includes/config.php        (sem admin-empresas.php)
  signature.php, admin-signature.php, request-job.php

APAGUE (tela de Empresas Parceiras removida):
  empresas.php
  admin-empresas.php
  includes/empresas.php
  data/empresas_parceiras.json   (se existir)
  uploads/empresas/              (se existir)

Quem ve cada bolinha:
  Admin   - icone Admin Vagas (engrenagem): solicitacoes de vaga dos alunos aguardando
          - icone Assinaturas: documentos solicitados e ainda nao atendidos
          - icone Painel de Egressos (so admin de egressos): vagas de egressos aguardando
          - icone Mentoria (so admin de egressos): mentores aguardando aprovacao
  Aluno   - icone Assinaturas: documento devolvido (assinado ou com erro) que ele ainda nao viu
  Egresso - icone Mentoria: pedidos de mentoria aguardando resposta

E-mails: aluno pede vaga -> admins | aluno envia/reenvia assinatura -> admins |
         admin devolve assinado -> aluno | admin marca erro -> aluno
