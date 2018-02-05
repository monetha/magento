/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/checkout-data',
        'Magento_Ui/js/modal/alert'
    ],
    function (        
        Component,
        placeOrderAction,
        selectPaymentMethodAction,
        checkoutData,
        alert,
        $) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Monetha_PaymentGateway/payment/form',
                transactionResult: ''
            },

            initObservable: function () {

                this._super()
                    .observe([
                        'transactionResult'
                    ]);
                return this;
            },

            getCode: function() {
                return 'monetha_gateway';
            },

            placeOrder: function() {
                if (event) {
                    event.preventDefault();
                }
                var self = this,
                    placeOrder,
                    loginFormSelector = 'form[data-role=email-with-possible-login]';
               
                if (this.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

                    jQuery.when(placeOrder).fail(function () {
                        self.isPlaceOrderActionAllowed(true);
                        console.error('"MONETHA: Order failed');
                    }).done( function() {
                        var config = window.checkoutConfig.payment.monetha_gateway;
                        if (config)
                            if (config.monetha_error !== "") {
                                console.error("MONETHA: " + config.monetha_error);
                                jQuery('.mth-payment-method-messages').html(config.monetha_error);
                                jQuery('.mth-payment-method-messages').show();
                            }
                            if (config.monetha_api !== "" &&
                                config.monetha_token !== "") {
                                var xhttp = new XMLHttpRequest(); 
                                xhttp.open("GET", config.monetha_api+"v1/deals/execute?token="+config.monetha_token, true)
                                xhttp.onreadystatechange = function(){
                                    if (this.readyState == 4 && this.status == 201) {
                                        var res = JSON.parse(this.responseText);
                                        window.location.href = res.order.payment_url;
                                    } else if (this.readyState == 4 && this.status >= 300) {
                                        jQuery('.mth-payment-method-messages').html(this.responseText);
                                        jQuery('.mth-payment-method-messages').show();
                                    }
                                }
                                xhttp.send();

                            } else {
                                console.error("MONETHA: Payment method configuration not complete");
                                jQuery('.mth-payment-method-messages').html('Payment method configuration not complete');
                                jQuery('.mth-payment-method-messages').show();
                            }
                    });
                    if (window.checkoutConfig.payment.monetha_gateway.monetha_error === "") {
                        return true;
                    }
                    else {
                        return false;
                    }
                }
                return false;
            },

            afterPlaceOrder: function () {
                
            },

            getTransactionResults: function() {
                
            }
        });
    }
);