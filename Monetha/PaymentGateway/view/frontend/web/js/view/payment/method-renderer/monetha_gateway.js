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
        'Magento_Checkout/js/checkout-data'
    ],
    function (        
        Component,
        placeOrderAction,
        selectPaymentMethodAction,
        checkoutData,
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
                        console.log('Order failed');
                    }).done( function() {
                        var config = window.checkoutConfig.payment.monetha_gateway;
                        if (config && 
                            config.merchant_project_id !== "" &&
                            config.merchant_secret_id !== "" &&
                            config.merchant_return_url !== "") {
                            
                            
                            
                            
                            var total = 0;
                            var now = new Date();
                            var warrantyValue = (new Date(now.getFullYear()+2,now.getMonth(), now.getDay())).toISOString();       
                            var paymentItems = [];
                            config.cart.items.forEach(function(item){
                                var paymentItem = {
                                    "name" : item.name, 
                                    "quantity": item.quantity, 
                                    "warranty": warrantyValue, 
                                    "price": item.price, 
                                    "subtotal": item.price * item.quantity, 
                                    "total_tax": 0, 
                                    "total": item.price * item.quantity
                                };
                                paymentItems.push(paymentItem);
                            });

                            var mageCacheStorage = JSON.parse(window.localStorage.getItem("mage-cache-storage"));
                        
                            if (mageCacheStorage === undefined) {
                                console.error("MONETHA: Magento cache storage undefined");
                                return;
                            }        

                            var cart = mageCacheStorage.cart;
                            var params = "pid="+config.merchant_project_id; 
                                params += "&secret="+config.merchant_secret_id; 
                                params += "&oid="+cart.data_id;
                                params +="&amount="+config.cart.grand_total;
                                params +="&currency="+config.cart.currency; 
                                params += "&return="+config.merchant_return_url;
                                params += "&cancel=";
                                params += "&callback=http://payment.monetha.io/monethabutton/callback";
                                params +="&i_firstname=";
                                params += "&i_lastname="+mageCacheStorage.customer.fullname;
                                params += "&i_email=merchants@monetha.io";
                                params +='&i_items='+JSON.stringify(paymentItems); 
                                params += "&i_delivery=post";                            

                            window.location.href = "https://payment.monetha.io/orders/add?"+params;

                        } else {
                            console.error("MONETHA: Payment method configuration not complete");
                        }
                    });
                    return true;
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