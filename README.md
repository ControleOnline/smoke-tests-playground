# Smoke Tests Playground

Bundle Symfony para expor uma API de conferência do último smoke test e um endpoint para disparar uma nova execução.
A interface humana fica em `GET /tests/ui` e consome a API.

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
- `GET /tests/ui`

## Respostas

`GET /tests` devolve um JSON com:

- `status`
- `generatedAt`
- `suite`
- `testsPath`
- `reportPath`
- `runCommand`
- `runWorkingDirectory`
- `runTimeout`
- `report`

`POST /tests/run` executa o comando configurado, devolve o mesmo payload de `GET /tests` e adiciona:

- `run.successful`
- `run.exitCode`
- `run.output`
- `run.errorOutput`
- `runRequestedAt`
- `requestedMethod`

`GET /tests/ui` entrega uma página HTML que consome `GET /tests` e `POST /tests/run` via `fetch`.

## Formato esperado do relatório

O controller lê `report.json` dentro do diretório configurado em `SMOKE_TESTS_PLAYGROUND_TESTS_PATH`.
As screenshots podem ser salvas como arquivos PNG relativos ao mesmo diretório, e a API embute as imagens no JSON como `src` em base64 para conferência rápida.
