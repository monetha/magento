# Magento

Monetha payment gateway integration with Magento 2

Detailed install and configuration guide will be available on our website - http://ico.monetha.io/en/mvp/

Contact email for your questions: team@monetha.io

# Technical guide
1. Copy Monetha folder into `app/code`
2. Disable Magento cache with `php bin/magento cache:disable`
3. Install the extension with `php bin/magento setup:upgrade`
4. Enable Magento cache with `php bin/magento cache:enable`
5. Re-deploy static content of Magento in case if it missing after upgrade `php bin/magento setup:static-content:deploy`
6. Configure merchant key, merchant secret, and payment method title in Magento Payment administration

In order to to try our integration in test mode please make sure to check "TestMode" check mark and use merchant key and secret provided below:

**Merchant Key:** MONETHA_SANDBOX_KEY

**Merchant Secret:** MONETHA_SANDBOX_SECRET

When test mode is switched on all payment transactions will be made in Ropsten testnet. Make sure not to send money from Ropsten testnet wallet address.
