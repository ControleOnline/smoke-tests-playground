# Smoke Tests Playground

Bundle Symfony para expor a UI de conferência do último smoke test e os endpoints de API para consultar e disparar execuções.
A interface humana fica em `GET /tests`, é renderizada por Twig e consome a API em `GET /tests/api` e `POST /tests/run`.

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

- `GET /tests` - UI HTML
- `GET /tests/api` - JSON com o relatório mais recente
- `POST /tests/run` - JSON com a execução de um novo smoke
- `GET /tests/ui` - alias da UI para compatibilidade

## Respostas

`GET /tests/api` devolve um JSON com:

- `status`
- `generatedAt`
- `suite`
- `testsPath`
- `reportPath`
- `runCommand`
- `runWorkingDirectory`
- `runTimeout`
- `report`

`POST /tests/run` executa o comando configurado, devolve o mesmo payload de `GET /tests/api` e adiciona:

- `run.successful`
- `run.exitCode`
- `run.output`
- `run.errorOutput`
- `runRequestedAt`
- `requestedMethod`

`GET /tests` entrega uma página HTML renderizada por Twig que consome `GET /tests/api` e `POST /tests/run` via `fetch`.

## Estrutura

- `src/Controller/SmokeTestsController.php` mantém só as rotas
- `src/Service/SmokeTestsPayloadFactory.php` monta os payloads JSON
- `src/Service/SmokeTestsPageRenderer.php` e `src/Service/SmokeTestsPageContextFactory.php` cuidam da UI
- `templates/smoke_tests_playground/` contém o HTML, os estilos e o JavaScript separados

## Formato esperado do relatório

O controller lê `report.json` dentro do diretório configurado em `SMOKE_TESTS_PLAYGROUND_TESTS_PATH`.
As screenshots podem ser salvas como arquivos PNG relativos ao mesmo diretório, e a API embute as imagens no JSON como `src` em base64 para conferência rápida.
