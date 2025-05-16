# Microsserviço Integra

Este microsserviço é responsável por gerenciar as integrações externas do sistema, incluindo serviços como **IZA**, **iFood**, entre outros que fazem parte das nossas integrações de delivery. Ele utiliza PHP 8.2 e depende da extensão PHPRedis, que precisa ser instalada no sistema. Além disso, este microsserviço possui testes unitários implementados.

## Configuração do ambiente

### Pré-requisitos

Certifique-se de ter o Homebrew e o PECL instalados em seu sistema.

- [Homebrew](https://brew.sh/)
- [PECL](https://pecl.php.net/)

### Instalação do PHP 8.2

1. Instale o PHP 8.2 usando Homebrew:
    ```sh
    brew install shivammathur/php/php@8.2
    ```
2. Force o link simbólico do PHP 8.2:
    ```sh
    brew link --force --overwrite php@8.2
    ```
3. Verifique a versão instalada do PHP:
    ```sh
    php -v
    ```
4. Instalar o XDEBUG
    ```sh
    pecl install xdebug
    ```

### Instalação das Dependências do Composer

#### Diretório `www`

1. Navegue até o diretório do projeto:
    ```sh
    cd www
    ```
2. Instale as dependências do Composer:
    ```sh
    composer install
    ```

#### Diretório `www/src`

1. Navegue até o diretório `src` dentro do projeto:
    ```sh
    cd www/src
    ```
2. Instale as dependências do Composer:
    ```sh
    composer install
    ```

> **Nota:** Dentro da pasta `src`, estamos utilizando as dependências para PHP 8.0, enquanto na pasta `www`, estamos com as dependências para PHP 8.2 para rodar os testes unitários da melhor forma. Em breve, migraremos para PHP 8.2 em produção e em ambientes de desenvolvimento e homologação.

### Instalação do PHPRedis

1. Instale a extensão PHPRedis:
    ```sh
    yes '' | pecl install redis
    ```
2. Verifique o caminho do arquivo de configuração do PHP:
    ```sh
    php --ini
    ```

    O resultado deve ser algo como:
    ```
    Configuration File (php.ini) Path: /opt/homebrew/etc/php/8.2
    Loaded Configuration File:         /opt/homebrew/etc/php/8.2/php.ini
    Scan for additional .ini files in: /opt/homebrew/etc/php/8.2/conf.d
    Additional .ini files parsed:      /opt/homebrew/etc/php/8.2/conf.d/ext-opcache.ini
    ```

3. Edite o arquivo `php.ini` para adicionar a extensão `redis`:
    _Você deve pegar o `Configuration File`, exemplo:_
    ```sh
    sudo nano /opt/homebrew/etc/php/8.2/php.ini
    ```
    - Pesquise por `extension=curl` usando `CTRL + W`.
    - Cole a linha `extension=redis.so` agrupada com as demais extensões (sem o `;` no início).

4. Verifique se a extensão foi carregada corretamente:
    ```sh
    php -v
    ```
    - Não deve haver nenhum erro na saída do comando.

## Testes Unitários

Os testes unitários estão localizados no diretório `www/tests/Unit`. Para rodar os testes, utilize os scripts definidos no arquivo `composer.json`.

### Comandos de Teste

Os comandos abaixo são aliases definidos no `composer.json` para facilitar a execução dos testes:

- `composer test`
    - Executa todos os testes.
- `composer test-profile`
    - Executa todos os testes e exibe o perfil de execução.
- `composer test-compact`
    - Executa todos os testes e exibe a saída em um formato compacto.
- `composer test-coverage`
    - Executa todos os testes com cobertura de código e exige uma cobertura mínima de 80%.
- `composer test-coverage-html`
    - Executa todos os testes com cobertura de código e gera um relatório em HTML na pasta `./tests-result`.

Certifique-se de que todos os testes unitários estejam passando antes de realizar a criação do PR.

## Workflow de Testes Unitários

Para garantir a qualidade do código, utilizamos o GitHub Actions para rodar testes unitários automaticamente em cada pull request. A configuração do workflow é definida no arquivo `.github/workflows/unit-tests.yml`.

### Detalhes da Configuração
Este workflow é disparado automaticamente em cada pull request e realiza as seguintes etapas:

1. **Checkout do repositório:** Faz o checkout do código do repositório.
2. **Configuração do PHP:** Instala o PHP 8.2 e configura o Composer e o Xdebug para cobertura de código.
3. **Cache de dependências do Composer:** Armazena em cache as dependências do Composer para acelerar as execuções futuras.
4. **Instalação das dependências:** Instala as dependências do Composer no diretório `www`.
5. **Execução dos testes:** Executa os testes unitários utilizando o Pest, com cobertura de código mínima exigida de **15%**.

# Alterações realizadas para a adoção da pipeline:

## Criação de um arquivo de pipeline no .github
Esse arquivo vai ser ativado sempre que uma nova tag for criada no repositório e realizará o Build e Push da imagem Docker no repositorio ghcr

## Criação de uma Dockerfile
Arquivo que será utilizado para gerar a imagem Docker da aplicação

## Criação de um arquivo .conf.template
Esse arquivo terá wildcards que serão substituídas por variáveis de ambiente ao rodar o entrypoint

## Movimentação dos arquivos de Cron para a pasta Scripts
Coloquei os arquivos pertencentes ao repositorio de Cron junto com os demais arquivos de Sripts

## Criação de um arquivo entrypoint
Arquivo criado para rodar os comandos necessários em tempo de execução, bem como a substituição dos wildcards dos arquivos de .conf