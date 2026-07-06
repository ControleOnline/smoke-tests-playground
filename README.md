# Smoke Tests Playground

Bundle Symfony para expor uma página de conferência do último smoke test e um botão para disparar uma nova execução.

## Instalação

1. Instale o pacote com Composer.
2. Registre o bundle no `config/bundles.php` do projeto consumidor:

```php
ControleOnline\SmokeTestsPlayground\SmokeTestsPlaygroundBundle::class => ['all' => true],
```

3. Execute o comando de bootstrap:

```bash
php bin/console smoke-tests-playground:install
```

O comando cria:

- `.env.local` com os defaults do smoke
- `config/routes/smoke_tests_playground.yaml`
- `config/services/smoke_tests_playground.yaml`

## Variáveis de ambiente

- `SMOKE_TESTS_PLAYGROUND_TESTS_PATH`: diretório onde ficam os artifacts e o `report.json`
- `SMOKE_TESTS_PLAYGROUND_RUN_COMMAND`: comando usado pelo botão "Rodar novos testes"
- `SMOKE_TESTS_PLAYGROUND_RUN_WORKDIR`: diretório de execução do comando
- `SMOKE_TESTS_PLAYGROUND_RUN_TIMEOUT`: timeout em segundos

## Rotas

- `GET /tests`
- `POST /tests/run`

## Formato esperado do relatório

O controller lê `report.json` dentro do diretório configurado em `SMOKE_TESTS_PLAYGROUND_TESTS_PATH`.
As screenshots podem ser salvas como arquivos PNG relativos ao mesmo diretório, e a página embute as imagens no HTML para conferência rápida.
