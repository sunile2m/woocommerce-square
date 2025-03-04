import '../../css/admin/wc-square-payment-gateway-admin-order.scss';

/*
 WooCommerce Square Payment Gateway Framework Order Admin
 */

( function () {
	jQuery( document ).ready( function ( $ ) {
		'use strict';
		let accounting,
			ref,
			ref1,
			ref2,
			ref3,
			submitCapture,
			sv_wc_payment_gateway_admin_order,
			woocommerce_admin,
			woocommerce_admin_meta_boxes;
		sv_wc_payment_gateway_admin_order =
			( ref = window.sv_wc_payment_gateway_admin_order ) != null
				? ref
				: {};
		woocommerce_admin =
			( ref1 = window.woocommerce_admin ) != null ? ref1 : {};
		woocommerce_admin_meta_boxes =
			( ref2 = window.woocommerce_admin_meta_boxes ) != null ? ref2 : {};
		accounting = ( ref3 = window.accounting ) != null ? ref3 : {};
		$( '.sv-wc-payment-gateway-partial-capture' ).appendTo(
			'#woocommerce-order-items .inside'
		);
		if ( '' !== sv_wc_payment_gateway_admin_order.has_gift_card ) {
			$( '#refund_amount' ).prop( 'readonly', true );
		}
		$( '#woocommerce-order-items' ).on(
			'click',
			'.wc-square-payment-gateway-capture:not(.disabled)',
			function ( e ) {
				e.preventDefault();
				if ( $( this ).hasClass( 'partial-capture' ) ) {
					$(
						'div.sv-wc-payment-gateway-partial-capture'
					).slideDown();
					$( 'div.wc-order-data-row-toggle' )
						.not( 'div.sv-wc-payment-gateway-partial-capture' )
						.slideUp();
					return $( 'div.wc-order-totals-items' ).slideUp();
				}
				return submitCapture();
			}
		);
		$( '.sv-wc-payment-gateway-partial-capture' ).on(
			'change keyup',
			'#capture_amount',
			function ( e ) {
				let total;
				total = accounting.unformat(
					$( this ).val(),
					woocommerce_admin.mon_decimal_point
				);
				if ( total ) {
					$( 'button.capture-action' ).removeAttr( 'disabled' );
				} else {
					$( 'button.capture-action' ).attr( 'disabled', 'disabled' );
				}
				return $( 'button .capture-amount .amount' ).text(
					accounting.formatMoney( total, {
						symbol:
							woocommerce_admin_meta_boxes.currency_format_symbol,
						decimal:
							woocommerce_admin_meta_boxes.currency_format_decimal_sep,
						thousand:
							woocommerce_admin_meta_boxes.currency_format_thousand_sep,
						precision:
							woocommerce_admin_meta_boxes.currency_format_num_decimals,
						format: woocommerce_admin_meta_boxes.currency_format,
					} )
				);
			}
		);
		$( '.sv-wc-payment-gateway-partial-capture' ).on(
			'click',
			'.capture-action',
			function ( e ) {
				let amount, comment;
				e.preventDefault();
				amount = $(
					'.sv-wc-payment-gateway-partial-capture #capture_amount'
				).val();
				comment = $(
					'.sv-wc-payment-gateway-partial-capture #capture_comment'
				).val();
				return submitCapture( amount, comment );
			}
		);

		/**
		 * Informs the merchant of billing details required for
		 * payment of a manual order.
		 */
		const handleBillingDetails =() => {
			const infoTextEl = $( '.square-billing-details-info' );
			const billingCountryEl = $( '#_billing_country' );

			billingCountryEl
				.on( 'change', function() {
					const billingCountry = billingCountryEl.val();

					if ( '' === billingCountry ) {
						infoTextEl.show();
					} else {
						infoTextEl.hide();
					}
				} )
				.trigger( 'change' );
		}

		handleBillingDetails();

		return ( submitCapture = function ( amount, comment ) {
			let data;
			if ( amount == null ) {
				amount = '';
			}
			if ( comment == null ) {
				comment = '';
			}
			if ( confirm( sv_wc_payment_gateway_admin_order.capture_ays ) ) {
				$( '#woocommerce-order-items' ).block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6,
					},
				} );
				data = {
					action: sv_wc_payment_gateway_admin_order.capture_action,
					nonce: sv_wc_payment_gateway_admin_order.capture_nonce,
					gateway_id: sv_wc_payment_gateway_admin_order.gateway_id,
					order_id: sv_wc_payment_gateway_admin_order.order_id,
					amount,
					comment,
				};
				return $.ajax( {
					url: sv_wc_payment_gateway_admin_order.ajax_url,
					data,
				} )
					.done( function ( response ) {
						if (
							response.data != null &&
							response.data.message != null
						) {
							alert( response.data.message );
						}
						if ( response.success ) {
							return location.reload();
						}
					} )
					.fail( function () {
						return alert(
							sv_wc_payment_gateway_admin_order.capture_error
						);
					} )
					.always( function () {
						return $( '#woocommerce-order-items' ).unblock();
					} );
			}
		} );
	} );
}.call( this ) );

//# sourceMappingURL=sv-wc-payment-gateway-admin-order.min.js.map
