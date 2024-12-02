# !! INSTRUCTIONS !!

To make PHP package from this source:

1. Replace `Php Preview Package` to `Your package name`
2. Replace `php-preview-package` to `your-package-name`
3. Run `make init`
4. Run `make docker`
5. Run `composer require --dev phpunit/phpunit ulovdomov/php-code-style phpstan/phpstan phpstan/phpstan-strict-rules`
6. Implement your code to `src` folder
7. Make sure to have correct value in `autoload.psr-4` in `composer.json` (see any other package for example)
8. Make at least one test in `tests` folder
   - Replace `BasicTest.php` with your own test
9. Create documentation in `README.md` and replace example `ExchangeRatesSdkClient`
10. Remove this INSTRUCTIONS section from this file

# Php Preview Package

Php Preview Package

## Installation

Add following to your `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/ulovdomov/php-preview-package"
    }
  ]
}
```

And run:

```shell
composer require ulovdomov/php-preview-package
```

## Nette DI extension

This is only example, must be replaced!

1. First need register and configure `UlovDomov\HttpClient\DI\HttpClientExtension`
2. And register `UlovDomov\ExchangeRatesSdk\DI\ExchangeRatesSdkClientExtension`

```neon
extensions:
    httpClient: UlovDomov\HttpClient\DI\HttpClientExtension
    exchangeRatesSdk: UlovDomov\ExchangeRatesSdk\DI\ExchangeRatesClientExtension
```

And configure it:

```neon
exchangeRatesSdk:
    apiUrl: 'https://data.kurzy.cz' # (optional) default is 'https://data.kurzy.cz'
```

## Usage

This is only example, must be replaced!

### Get exchange rates

```php
$client = new \UlovDomov\HttpClient\GuzzleHttpClient();

//create rest client or get it from DI container created by extension
$restClient = new \UlovDomov\ExchangeRatesSdk\ExchangeRatesClient(
    apiUrl: 'https://data.kurzy.cz',
    httpClient: $client,
);

$date = new \DateTime('2024-10-01');
$bankCode = \UlovDomov\ExchangeRatesSdk\BankCode::CNB;

/** @var array<\UlovDomov\ExchangeRatesSdk\ExchangeRate> $rates */
$rates = $restClient->getExchangeRates($date, $bankCode);
```

Example of ExchangeRate object:
```txt
14 => UlovDomov\ExchangeRatesSdk\ExchangeRate #48
   |  from: 'CZK'
   |  to: 'ZAR'
   |  amount: 1
   |  rate: 1.257
   |  buy: null
   |  sell: null
```

## Development

### First setup

1. Run for initialization
```shell
make init
```
2. Run composer install
```shell
make composer
```

Use tasks in Makefile:

- To log into container
```shell
make docker
```
- To run code sniffer fix
```shell
make cs-fix
```
- To run PhpStan
```shell
make phpstan
```
- To run tests
```shell
make phpunit
```