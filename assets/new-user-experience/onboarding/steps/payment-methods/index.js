/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
import { ToggleControl, Button } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import {
	SectionTitle,
	SectionDescription,
	InputWrapper
} from '../../../components';
import { Confetti, RightArrowInCircle } from '../../../icons';
import { usePaymentGatewaySettings, useSteps } from '../../hooks';

export const PaymentMethods = () => {
	const {
		isPaymentGatewaySettingsSaving,
		isCashAppGatewaySettingsSaving,
		isGiftCardsGatewaySettingsSaving,

		paymentGatewaySettings,
		cashAppGatewaySettings,
		giftCardsGatewaySettings,
		paymentGatewaySettingsLoaded,
		cashAppGatewaySettingsLoaded,

		setCreditCardData,
		setDigitalWalletData,
		setGiftCardData,
		setCashAppData,

		savePaymentGatewaySettings,
		saveCashAppSettings,
		saveGiftCardsSettings,
	} = usePaymentGatewaySettings();

	const {
		setStep,
	} = useSteps();

	const {
		enabled,
		enable_digital_wallets,
	} = paymentGatewaySettings;

	const enable_gift_cards = giftCardsGatewaySettings.enabled;
	const enable_cash_app = cashAppGatewaySettings.enabled;

	if ( ! ( paymentGatewaySettingsLoaded && cashAppGatewaySettingsLoaded ) ) {
		return null;
	}

	const isSavingState = [
		isPaymentGatewaySettingsSaving,
		isCashAppGatewaySettingsSaving,
		isGiftCardsGatewaySettingsSaving,
	].some( state => null === state || true === state );

	return (
		<div className="woo-square-onbarding__payment-settings">
			<div className="woo-square-onbarding__payment-settings--left">
				<div className="woo-square-onbarding__payment-settings__intro">
					<div className="woo-square-onbarding__payment-settings__intro--title">
						{ __( "You're connected to Square!", 'woocommerce-square' ) }
						<span className="woo-square-onbarding__payment-settings__intro--title-icon">
							<Confetti />
						</span>
					</div>
					<SectionDescription>
						{ __( "Congratulations! You've successfully connected your Square account.", 'woocommerce-square' ) }
						<p>
							{ __( "Now, let's enable the payment methods you want to offer on your site. This is where you can tailor your checkout experience to meet your customers' needs.", 'woocommerce-square' ) }
						</p>
					</SectionDescription>
				</div>
				<div className="woo-square-onbarding__payment-settings__center-icon">
					<RightArrowInCircle />
				</div>
			</div>
			<div className="woo-square-onbarding__payment-settings--right">
				<div className="woo-square-onbarding__payment-settings__toggles">
					<SectionTitle title={ __( 'Enable Payment Methods', 'woocommerce-square' ) } />
					<SectionDescription>
						{ __( 'Simply toggle the payment methods you wish to activate. Each method you enable here will be available to your customers at checkout, making their purchase process smooth and effortless.', 'woocommerce-square' ) }
					</SectionDescription>

					<InputWrapper
						label={ __( 'Enable Credit & Debit Cards', 'woocommerce-square' ) }
						variant="boxed"
					>
						<ToggleControl
							className="payment-gateway-toggle__credit-card"
							checked={ 'yes' === enabled }
							onChange={ ( enabled ) => setCreditCardData( { enabled: enabled ? 'yes' : 'no' } ) }
						/>
					</InputWrapper>

					<InputWrapper
						label={ __( 'Enable Digital Wallets', 'woocommerce-square' ) }
						variant="boxed"
					>
						<ToggleControl
							className="payment-gateway-toggle__digital-wallet"
							checked={ 'yes' === enable_digital_wallets }
							onChange={ ( enable_digital_wallets ) => setDigitalWalletData( { enable_digital_wallets: enable_digital_wallets ? 'yes' : 'no' } ) }
						/>
					</InputWrapper>

					<InputWrapper
						label={ __( 'Enable Cash App Pay', 'woocommerce-square' ) }
						variant="boxed"
					>
						<ToggleControl
							className="payment-gateway-toggle__cash-app"
							checked={ 'yes' === enable_cash_app }
							onChange={ ( enable_cash_app ) => setCashAppData( { enabled: enable_cash_app ? 'yes' : 'no' } ) }
						/>
					</InputWrapper>

					<InputWrapper
						label={ __( 'Enable Square Gift Cards', 'woocommerce-square' ) }
						variant="boxed"
					>
						<ToggleControl
							className="payment-gateway-toggle__gift-card"
							checked={ 'yes' === enable_gift_cards }
							onChange={ ( enable_gift_cards ) => setGiftCardData( { enabled: enable_gift_cards ? 'yes' : 'no' } ) }
						/>
					</InputWrapper>

					<div className="woo-square-onbarding__payment-settings__toggles__next-btn">
						<Button
							data-testid="next-step-button"
							variant="primary"
							isBusy={ isSavingState }
							disabled={ isSavingState }
							onClick={ () => {
								( async () => {
									await savePaymentGatewaySettings();
									await saveCashAppSettings();
									await saveGiftCardsSettings();
									setStep( 'payment-complete' );
								} )()
							} }
						>
							{ __( 'Next', 'woocommerce-square' ) }
						</Button>
					</div>
				</div>
			</div>
		</div>
	);
};