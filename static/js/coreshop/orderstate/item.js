/**
 * CoreShop
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015 Dominik Pfaffenbauer (http://dominik.pfaffenbauer.at)
 * @license    http://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */


pimcore.registerNS("pimcore.plugin.coreshop.orderstate.item");
pimcore.plugin.coreshop.orderstate.item = Class.create({

    initialize: function (parentPanel, data) {
        this.parentPanel = parentPanel;
        this.data = data;

        this.initPanel();
    },

    initPanel: function () {

        this.panel = new Ext.panel.Panel({
            title: this.data.name,
            closable: true,
            iconCls: "coreshop_icon_order_states",
            layout: "border",
            items : [this.getFormPanel()]
        });

        this.panel.on("beforedestroy", function () {
            delete this.parentPanel.panels["coreshop_order_state" + this.data.id];
        }.bind(this));

        this.parentPanel.getTabPanel().add(this.panel);
        this.parentPanel.getTabPanel().setActiveItem(this.panel);
    },

    activate : function() {
        this.parentPanel.getTabPanel().setActiveItem(this.panel);
    },

    getFormPanel : function()
    {
        var data = this.data;

        var langTabs = [];
        Ext.each(pimcore.settings.websiteLanguages, function(lang) {
            var tab = {
                title: pimcore.available_languages[lang],
                iconCls: "pimcore_icon_language_" + lang.toLowerCase(),
                layout:'form',
                items: [{
                    fieldLabel: t("coreshop_order_state_emailDocument"),
                    labelWidth: 350,
                    name: "emailDocument." + lang,
                    fieldCls: "pimcore_droptarget_input",
                    value: data.localizedFields.items[lang] ? data.localizedFields.items[lang].emailDocument : "",
                    xtype: "textfield",
                    listeners: {
                        "render": function (el) {
                            new Ext.dd.DropZone(el.getEl(), {
                                reference: this,
                                ddGroup: "element",
                                getTargetFromEvent: function(e) {
                                    return this.getEl();
                                }.bind(el),

                                onNodeOver : function(target, dd, e, data) {
                                    data = data.records[0].data;

                                    if (data.elementType == "document") {
                                        return Ext.dd.DropZone.prototype.dropAllowed;
                                    }
                                    return Ext.dd.DropZone.prototype.dropNotAllowed;
                                },

                                onNodeDrop : function (target, dd, e, data) {
                                    data = data.records[0].data;

                                    if (data.elementType == "document") {
                                        this.setValue(data.path);
                                        return true;
                                    }
                                    return false;
                                }.bind(el)
                            });
                        }
                    }
                }]
            };

            langTabs.push( tab );
        });

        this.formPanel = new Ext.form.Panel({
            bodyStyle:'padding:20px 5px 20px 5px;',
            border: false,
            region : "center",
            autoScroll: true,
            forceLayout: true,
            defaults: {
                forceLayout: true
            },
            buttons: [
                {
                    text: t("save"),
                    handler: this.save.bind(this),
                    iconCls: "pimcore_icon_apply"
                }
            ],
            items: [
                {
                    xtype:'fieldset',
                    autoHeight:true,
                    labelWidth: 350,
                    defaultType: 'textfield',
                    defaults: {width: '100%'},
                    items :[
                        {
                            xtype: "textfield",
                            name: "name",
                            fieldLabel: t("name"),
                            width: 400,
                            value: data.name
                        }, {
                            xtype: "checkbox",
                            name: "accepted",
                            fieldLabel: t("coreshop_order_state_accepted"),
                            width: 250,
                            checked: parseInt(data.accepted)
                        }, {
                            xtype: "checkbox",
                            name: "shipped",
                            fieldLabel: t("coreshop_order_state_shipped"),
                            width: 250,
                            checked: parseInt(data.shipped)
                        }, {
                            xtype: "checkbox",
                            name: "paid",
                            fieldLabel: t("coreshop_order_state_paid"),
                            width: 250,
                            checked: parseInt(data.paid)
                        }, {
                            xtype: "checkbox",
                            name: "invoice",
                            fieldLabel: t("coreshop_order_state_invoice"),
                            width: 250,
                            checked: parseInt(data.invoice)
                        }, {
                            xtype: "checkbox",
                            name: "email",
                            fieldLabel: t("coreshop_order_state_email"),
                            width: 250,
                            checked: parseInt(data.email)
                        }, {
                            xtype: "tabpanel",
                            activeTab: 0,
                            defaults: {
                                autoHeight:true,
                                bodyStyle:'padding:10px;'
                            },
                            items: langTabs
                        }
                    ]
                }
            ]
        });

        return this.formPanel;
    },

    save: function ()
    {
        var values = this.formPanel.getForm().getFieldValues();

        Ext.Ajax.request({
            url: "/plugin/CoreShop/admin_OrderStates/save",
            method: "post",
            params: {
                data: Ext.encode(values),
                id : this.data.id
            },
            success: function (response) {
                try {
                    var res = Ext.decode(response.responseText);
                    if (res.success) {
                        pimcore.helpers.showNotification(t("success"), t("coreshop_order_state_saved_successfully"), "success");
                    } else {
                        pimcore.helpers.showNotification(t("error"), t("coreshop_order_state_saved_error"),
                            "error", t(res.message));
                    }
                } catch(e) {
                    pimcore.helpers.showNotification(t("error"), t("coreshop_order_state_saved_error"), "error");
                }
            }
        });
    }
});