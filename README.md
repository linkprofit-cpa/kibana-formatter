# kibana-formatter

[![Latest Stable Version][Stable ver badge]][Stable ver src]
[![License][License badge]][License src]
[![Conventional Commits][Conventional commits badge]][Conventional commits src]
[![Semantic Versioning][Versioning img]][Versioning src]

## Kibana log-messages for exceptions
By default after formatting in [GelfMessageFormatter][GelfMessageFormatter.php] Exception traces in Kibana JSON-view looks like string.
This package formats traces into easy readable JSON.

## Symfony 4+ installation
1. Install logger:
    ```bash
    composer require logger
    ```
2. Install kibana-formatter:
    ```bash
    composer require linkprofit-cpa/kibana-formatter:^0.4
    ```
3. Add to `.env.dist` and `.env` `APPLICATION_CODE` and `APPLICATION_VERSION` variables.
4. Edit `config/packages/prod/monolog.yaml`:
    ```yaml
    monolog:
        handlers:
            graylog:
                type: gelf
                publisher:
                    id: gelf.kibana.publisher
                level: notice
                bubble: true
                formatter: Linkprofit\KibanaFormatter\KibanaMessageFormatter
    ```
    Recommended log level for *prod* and *test* environments - **notice**. For *dev* - **debug**.
5. Edit `config/services.yaml`:
    ```yaml
    services:
        ...
        Linkprofit\KibanaFormatter\KibanaMessage:
            arguments:
                - '%env(APPLICATION_CODE)%'
                - '%env(APPLICATION_VERSION)%'

        Linkprofit\KibanaFormatter\KibanaMessageValidator:

        Linkprofit\KibanaFormatter\KibanaMessageFormatter:
            class: Linkprofit\KibanaFormatter\KibanaMessageFormatter
            arguments:
                - '@Linkprofit\KibanaFormatter\KibanaMessage'

        gelf.kibana.publisher:
            class: Gelf\Publisher
            arguments:
                - '@gelf.kibana.transport'
                - '@Linkprofit\KibanaFormatter\KibanaMessageValidator'

        gelf.kibana.transport:
            class: Gelf\Transport\UdpTransport
            arguments: [elastic.lthost.net, 12201, 1420]
    ```
## Versioning
This software follows *"Semantic Versioning"* specifications. All function signatures declared as public API.

Read more on [SemVer.org](http://semver.org).

[Stable ver badge]: https://poser.pugx.org/linkprofit-cpa/kibana-formatter/v/stable
[Stable ver src]: https://poser.pugx.org/linkprofit-cpa/kibana-formatter/v/stable
[License badge]: https://poser.pugx.org/linkprofit-cpa/kibana-formatter/license
[License src]: https://packagist.org/packages/linkprofit-cpa/kibana-formatter
[Conventional commits src]: https://conventionalcommits.org
[Conventional commits badge]: https://img.shields.io/badge/Conventional%20Commits-1.0.0-yellow.svg
[Versioning img]: https://img.shields.io/badge/Semantic%20Versioning-2.0.0-brightgreen.svg
[Versioning src]: https://semver.org
[GelfMessageFormatter.php]: https://github.com/Seldaek/monolog/blob/master/src/Monolog/Formatter/GelfMessageFormatter.php
