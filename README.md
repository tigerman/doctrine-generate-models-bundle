# DoctrineGenerateModelsBundle

Symfony2 bundle to generate model classes from your mapping information from YAML.


## Installation (Composer):

Add the following dependencies to your projects composer.json file:


```json
{
    "require": {
        "tigerman/doctrine-generate-models-bundle": "*"
    }
}
```

Finally, be sure to enable the bundle in AppKernel.php by including the following:

```php
// app/AppKernel.php
public function registerBundles()
{
    $bundles = array(
        //...
        new tigerman\DoctrineGenerateModelsBundle\DoctrineGenerateModelsBundle(),
    );
}
```

## Usage:
```bash
php app/console doctrine:generate:models
```
