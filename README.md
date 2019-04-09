# Magento

Monetha payment gateway integration with Magento 2

Detailed install and configuration guide is available at https://help.monetha.io/hc/en-us/articles/360002550172-Magento-2-integration

Contact email for your questions: team@monetha.io

# Technical guide
1. Copy Monetha folder into `app/code`
2. `php ./vendor/composer/composer/bin/composer config repositories.monetha/payment-plugin-php-sdk vcs https://github.com/monetha/payment-plugin-php-sdk.git`
3. `php ./vendor/composer/composer/bin/composer require monetha/payment-plugin-php-sdk:dev-master` 
(in case if you're updating the plugin version, run `php ./vendor/composer/composer/bin/composer update monetha/payment-plugin-php-sdk`) instead
4. Disable Magento cache with `php bin/magento cache:disable`
5. Install the extension with `php bin/magento setup:upgrade`
6. Enable Magento cache with `php bin/magento cache:enable`
7. Re-deploy static content of Magento in case if it missing after upgrade `php bin/magento setup:static-content:deploy`
8. Configure merchant key, merchant secret, and payment method title in Magento Payment administration

Access tokens for `repo.magento.com` (if required) you can get here https://marketplace.magento.com/customer/accessKeys/

Issues that you may encounter on the 2-3 steps can be related with outdated build-in composer binary. 

In this case download newest composer.phar and run it instead like `php /path/to/composer.phar ...` or just `composer ...` if you have it in your $PATH).

In order to to try our integration in test mode please make sure to check "TestMode" check mark and use merchant key and secret provided below:

**Merchant Key:** MONETHA_SANDBOX_KEY

**Merchant Secret:** MONETHA_SANDBOX_SECRET

When test mode is switched on all payment transactions will be made in Ropsten testnet. Make sure not to send money from Ropsten testnet wallet address.


### If you have any questions or requests, feel free to ask them via support@monetha.io