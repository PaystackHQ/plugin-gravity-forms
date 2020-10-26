<?php

defined('ABSPATH') || die();

// Include the payment add-on framework.
GFForms::include_payment_addon_framework();

/**
 * Class GFPaystack
 *
 * Primary class to manage the Paystack add-on.
 *
 * @since 1.0
 *
 * @uses GFPaymentAddOn
 */
class GFPaystack extends GFPaymentAddOn
{
	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @used-by GFPaystack::get_instance()
	 *
	 * @var object $_instance If available, contains an instance of this class.
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Paystack Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @used-by GFPaystack::scripts()
	 *
	 * @var string $_version Contains the version, defined from paystack.php
	 */
	protected $_version = GF_PAYSTACK_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @var string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = '2.0';

	/**
	 * Defines the plugin slug.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @var string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformspaystack';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @var string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformspaystack/paystack.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @var string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this Add-On can be found.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @var string $_url The URL of the Add-On.
	 */
	protected $_url = 'http://www.paystack.com';

	/**
	 * Defines the title of this Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @var string $_title The title of the Add-On.
	 */
	protected $_title = 'Paystack Add-On for Gravity Forms';

	/**
	 * Defines the short title of the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @var string $_short_title The short title.
	 */
	protected $_short_title = 'Paystack';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @var bool $_enable_rg_autoupgrade true
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines if user will not be able to create feeds for a form until a credit card field has been added.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @var bool $_requires_credit_card false.
	 */
	protected $_requires_credit_card = false;

	/**
	 * Defines if callbacks/webhooks/IPN will be enabled and the appropriate database table will be created.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @var bool $_supports_callbacks true
	 */
	protected $_supports_callbacks = true;

	/**
	 * Paystack requires monetary amounts to be formatted as the smallest unit for the currency being used e.g. kobo, pesewas, cent
	 *
	 * @since  1.10.1
	 * @access protected
	 *
	 * @var bool $_requires_smallest_unit true
	 */
	protected $_requires_smallest_unit = true;

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_paystack';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_paystack';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_paystack_uninstall';

	/**
	 * Defines the capabilities needed for the Paystack Add-On
	 *
	 * @since  1.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array('gravityforms_paystack', 'gravityforms_paystack_uninstall');

	/**
	 * Holds the custom meta key currently being processed. Enables the key to be passed to the gform_paystack_field_value filter.
	 *
	 * @since  1.0
	 * @access protected
	 *
	 * @used-by GFPaystack::maybe_override_field_value()
	 *
	 * @var string $_current_meta_key The meta key currently being processed.
	 */
	protected $_current_meta_key = '';

	/**
	 * Enable rate limits to log card errors etc.
	 *
	 * @since 1.0
	 *
	 * @var bool
	 */
	protected $_enable_rate_limits = true;

	/**
	 * Paystack API Wrapper
	 *
	 * @var GFPaystackApi
	 */
	protected $paystack_api;

	/**
	 * Get an instance of this class.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFPaystack
	 * @uses GFPaystack::$_instance
	 *
	 * @return object GFPaystack
	 */
	public static function get_instance()
	{
		if (null === self::$_instance) {
			self::$_instance = new GFPaystack();
		}

		return self::$_instance;
	}

	/**
	 * Load the Paystack credit card field.
	 *
	 * @since 1.0
	 */
	public function pre_init()
	{
		// For form confirmation redirection, this must be called in `wp`,
		// or confirmation redirect to a page would throw PHP fatal error.
		// Run before calling parent method. We don't want to run anything else before displaying thank you page.
		add_action('wp', array($this, 'maybe_thankyou_page'), 5);

		parent::pre_init();
	}

	public function init()
	{
		parent::init();

		add_filter('gform_currencies', array($this, 'add_paystack_currencies'));
	}

	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @access public
	 *
	 * @used-by GFAddOn::maybe_save_plugin_settings()
	 * @used-by GFAddOn::plugin_settings_page()
	 * @uses  GFPaystack::api_settings_fields()
	 * @uses  GFPaystack::get_webhooks_section_description()
	 *
	 * @return array Plugin settings fields to add.
	 */
	public function plugin_settings_fields()
	{
		$fields = array(
			array(
				'title'  => esc_html__('Configuration', 'gravityformspaystack'),
				'fields' => $this->api_settings_fields(),
			),
		);

		return $fields;
	}

	/**
	 * Define the settings which appear in the Paystack API section.
	 *
	 * @access public
	 *
	 * @used-by GFPaystack::plugin_settings_fields()
	 *
	 * @return array The API settings fields.
	 */
	public function api_settings_fields()
	{
		$api_mode = '';

		if ($this->is_detail_page() && empty($api_mode)) {
			$api_mode = $this->get_plugin_setting('api_mode');
		}

		$fields = array(
			array(
				'name'          => 'api_mode',
				'label'         => esc_html__('Mode', 'gravityformspaystack'),
				'type'          => 'radio',
				'default_value' => $api_mode,
				'choices'       => array(
					array(
						'label' => esc_html__('Live', 'gravityformspaystack'),
						'value' => 'live',
					),
					array(
						'label' => esc_html__('Test', 'gravityformspaystack'),
						'value' => 'test',
					),
				),
				'horizontal'    => true,
			)
		);

		$credentials_fields = array(
			array(
				'name'  => 'test_public_key',
				'label' => esc_html__('Test Public Key', 'gravityformspaystack'),
				'type'  => 'text',
				'class'    => 'medium',
			),
			array(
				'name'  => 'test_secret_key',
				'label' => esc_html__('Test Secret Key', 'gravityformspaystack'),
				'type'  => 'text',
				'class'    => 'medium',
			),
			array(
				'name'  => 'live_public_key',
				'label' => esc_html__('Live Public Key', 'gravityformspaystack'),
				'type'  => 'text',
				'class'    => 'medium',
			),
			array(
				'name'  => 'live_secret_key',
				'label' => esc_html__('Live Secret Key', 'gravityformspaystack'),
				'type'  => 'text',
				'class'    => 'medium',
			),
		);

		$fields = array_merge($fields, $credentials_fields);

		$webhook_fields = array(
			array(
				'name'        => 'webhooks_enabled',
				'label'       => esc_html__('Webhooks Enabled?', 'gravityformspaystack'),
				'type'        => 'checkbox',
				'horizontal'  => true,
				'required'    => ($this->get_current_feed_id() || !isset($_GET['fid'])) ? true : false,
				'description' => $this->get_webhooks_section_description(),
				'dependency'  => $this->is_detail_page() ? array($this, 'is_feed_paystack_connect_enabled') : false,
				'choices'     => array(
					array(
						'label' => esc_html__('I have enabled the Gravity Forms webhook URL in my Paystack account.', 'gravityformspaystack'),
						'value' => 1,
						'name'  => 'webhooks_enabled',
					),
				),
			)
		);

		$fields = array_merge($fields, $webhook_fields);

		return $fields;
	}

	/**
	 * Define the markup to be displayed for the webhooks section description.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFPaystack::plugin_settings_fields()
	 * @uses    GFPaystack::get_webhook_url()
	 *
	 * @return string HTML formatted webhooks description.
	 */
	public function get_webhooks_section_description()
	{
		ob_start();
?>
		<a href="javascript:void(0);" onclick="tb_show('Webhook Instructions', '#TB_inline?width=500&inlineId=paystack-webhooks-instructions', '');" onkeypress="tb_show('Webhook Instructions', '#TB_inline?width=500&inlineId=paystack-webhooks-instructions', '');">
			<?php esc_html_e('View Instructions', 'gravityformspaystack'); ?>
		</a>
		</p>

		<div id="paystack-webhooks-instructions" style="display:none;">
			<ol class="paystack-webhooks-instructions">
				<li>
					<p><?php esc_html_e('Click the following link and log in to access your Paystack Webhooks management page:', 'gravityformspaystack'); ?> </p>
					<a href="https://dashboard.paystack.com/#/settings/developer" target="_blank">https://dashboard.paystack.com/#/settings/developer</a>
				</li>
				<li>
					<p>
						<?php esc_html_e('Enter the following URL in the "Webhook URL" field:', 'gravityformspaystack'); ?>
						<br />
						<code><?php echo $this->get_webhook_url($this->get_current_feed_id()); ?></code>
					</p>
				</li>
				<li><?php esc_html_e('Save Changes.', 'gravityformspaystack'); ?></li>
			</ol>
		</div>

<?php
		return ob_get_clean();
	}

	// # FEED SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @access public
	 *
	 * @uses GFPaymentAddOn::feed_settings_fields()
	 * @uses GFAddOn::replace_field()
	 * @uses GFAddOn::get_setting()
	 * @uses GFAddOn::add_field_after()
	 * @uses GFAddOn::remove_field()
	 * @uses GFAddOn::add_field_before()
	 *
	 * @return array The feed settings.
	 */
	public function feed_settings_fields()
	{
		// Get default payment feed settings fields.
		$default_settings = parent::feed_settings_fields();

		$fields = array(
			array(
				'name'          => 'mode',
				'label'         => esc_html__('Mode', 'gravityformspaystack'),
				'type'          => 'radio',
				'choices'       => array(
					array('id' => 'gf_paystack_live_mode', 'label' => esc_html__('Live', 'gravityformspaystack'), 'value' => 'live'),
					array('id' => 'gf_paystack_test_mode', 'label' => esc_html__('Test', 'gravityformspaystack'), 'value' => 'test'),
				),
				'horizontal'    => true,
				'default_value' => 'live',
				'tooltip'       => '<h6>' . esc_html__('Mode', 'gravityformspaystack') . '</h6>' . esc_html__('Select Live to receive payments in production. Select Test for testing purposes when using the Paystack development sandbox.', 'gravityformspaystack')
			),
		);

		$default_settings = parent::add_field_after('feedName', $fields, $default_settings);

		// Add donation to transaction type drop down 
		$transaction_type = parent::get_field('transactionType', $default_settings);
		$choices          = $transaction_type['choices'];
		$add_donation     = true;

		foreach ($choices as $choice) {
			// Add donation option if it does not already exist
			if ($choice['value'] == 'donation') {
				$add_donation = false;
			}
		}

		if ($add_donation) {
			// Add donation transaction type
			$choices[] = array('label' => __('Donations', 'gravityformspaystack'), 'value' => 'donation');
		}

		$transaction_type['choices'] = $choices;
		$default_settings = $this->replace_field('transactionType', $transaction_type, $default_settings);

		// Add send invoice field if the feed transaction type is a subscription.
		if ($this->get_setting('transactionType') === 'subscription') {
			$invoice_settings = array(
				'name'    => 'send_invoices',
				'label'   => esc_html__('Send Invoices', 'gravityformspaystack'),
				'type'    => 'receipt',
				'tooltip' => '<h6>' . esc_html__('Send Paystack Invoices', 'gravityformspaystack') . '</h6>' . esc_html__('Enable send invoices. Paystack can send a invoice via email to customers.', 'gravityformspaystack'),
			);

			$default_settings = $this->add_field_before('setupFee', $invoice_settings, $default_settings);
		}

		// hide default display of setup fee, not used by Paystack
		$default_settings = parent::remove_field('setupFee', $default_settings);

		// // Prepare trial period field.
		// $trial_period_field = array(
		// 	'name'                => 'trialPeriod',
		// 	'label'               => esc_html__('Trial Period', 'gravityformspaystack'),
		// 	'style'               => 'width:40px;text-align:center;',
		// 	'type'                => 'trial_period',
		// 	'after_input'         => '&nbsp;' . esc_html__('days', 'gravityformspaystack'),
		// 	'validation_callback' => array($this, 'validate_trial_period'),
		// );

		// // Add trial period field.
		// $default_settings = $this->add_field_after('trial', $trial_period_field, $default_settings);

		// Hide default display of trial, not used by Paystack
		$default_settings = parent::remove_field('trial', $default_settings);

		// Add subscription name field.
		$plan_name_field = array(
			'name'    => 'plan_name',
			'label'   => esc_html__('Plan Name', 'gravityformspaystack'),
			'type'    => 'text',
			'class'   => 'medium merge-tag-support mt-hide_all_fields mt-position-right',
			'tooltip' => '<h6>' . esc_html__('Plan Name', 'gravityformspaystack') . '</h6>' . esc_html__('Enter a name for the subscription. It will be displayed on the payment form as well as the Paystack dashboard.', 'gravityformspaystack'),
		);

		$default_settings  = $this->add_field_before('recurringAmount', $plan_name_field, $default_settings);

		// Customer information fields.
		$customer_info_field = array(
			'name'       => 'customerInformation',
			'label'      => esc_html__('Customer Information', 'gravityformspaystack'),
			'type'       => 'field_map',
			'field_map'  => array(
				array(
					'name'       => 'email',
					'label'      => esc_html__('Email', 'gravityformspaystack'),
					'required'   => true,
					'field_type' => array('email', 'hidden'),
					'tooltip'    => '<h6>' . esc_html__('Email', 'gravityformspaystack') . '</h6>' . esc_html__('You can specify an email field and it will be sent to the Paystack screen as the customer\'s email.', 'gravityformspaystack'),
				),
				array(
					'name'     => 'first_name',
					'label'    => esc_html__('First Name', 'gravityformspaystack'),
					'required' => ($this->get_setting('transactionType') == 'subscription') ? true : false,
				),
				array(
					'name'     => 'last_name',
					'label'    => esc_html__('Last Name', 'gravityformspaystack'),
					'required' => ($this->get_setting('transactionType') == 'subscription') ? true : false,
				)
			),
		);

		// Add customer information fields.
		$default_settings = $this->add_field_before('billingInformation', $customer_info_field, $default_settings);

		// Prepare meta data field.
		$custom_meta = array(
			array(
				'name'                => 'metaData',
				'label'               => esc_html__('Metadata', 'gravityformspaystack'),
				'type'                => 'dynamic_field_map',
				'limit'				  => 20,
				'tooltip'             => '<h6>' . esc_html__('Metadata', 'gravityformspaystack') . '</h6>' . esc_html__('You may send custom meta information to Paystack. A maximum of 20 custom keys may be sent.', 'gravityformspaystack'),
				'validation_callback' => array($this, 'validate_custom_meta'),
			),
		);

		// Add meta data field.
		$default_settings = $this->add_field_after('billingInformation', $custom_meta, $default_settings);

		// hide default display of billing Information if transaction type is donation
		if ($this->get_setting('transactionType') === 'donation') {
			$default_settings = parent::remove_field('billingInformation', $default_settings);
		}

		// // Add Paystack Credential section.
		// $default_settings[] = array(
		// 	'title'      => esc_html__('Paystack Configuration', 'gravityformspaystack'),
		// 	'fields'     => $this->api_settings_fields(),
		// 	'dependency' => array(
		// 		'field'  => 'transactionType',
		// 		'values' => array('subscription', 'product', 'donation'),
		// 	),
		// );

		return $default_settings;
	}

	/**
	 * Define the markup for the billing_cycle type field.
	 *
	 * @access public
	 *
	 * @return array The feed settings.
	 */
	public function settings_billing_cycle($field, $echo = true)
	{
		$intervals = $this->supported_billing_intervals();

		$choices = array();
		foreach ($intervals as $unit => $interval) {
			if (!empty($interval)) {
				$choices[] = array('value' => $unit, 'label' => $interval['label']);
			}
		}

		//Interval drop down
		$interval_field = array(
			'name'     => $field['name'] . '_unit',
			'type'     => 'select',
			'onchange' => "loadBillingLength('" . esc_attr($field['name']) . "')",
			'choices'  => $choices,
		);

		$html = '&nbsp' . $this->settings_select($interval_field, false);

		$html .= "<script type='text/javascript'>var " . $field['name'] . '_intervals = ' . json_encode($intervals) . ';</script>';

		if ($echo) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Define the choices available in the billing cycle dropdowns.
	 *
	 * @access public
	 *
	 * @used-by GFPaymentAddOn::settings_billing_cycle()
	 *
	 * @return array Billing intervals that are supported.
	 */
	public function supported_billing_intervals()
	{
		return array(
			'hourly'   => array('label' => esc_html__('Hourly', 'gravityformspaystack')),
			'daily'    => array('label' => esc_html__('Daily', 'gravityformspaystack')),
			'weekly'   => array('label' => esc_html__('Weekly', 'gravityformspaystack')),
			'monthly'  => array('label' => esc_html__('Monthly', 'gravityformspaystack')),
			'annually'   => array('label' => esc_html__('Annually', 'gravityformspaystack')),
			'biannually' => array('label' => esc_html__('Biannually', 'gravityformspaystack')),
		);
	}

	/**
	 * Define the markup for the receipt type field.
	 *
	 * @access public
	 *
	 * @param array     $field The field properties. Not used.
	 * @param bool|true $echo  Should the setting markup be echoed. Defaults to true.
	 *
	 * @return string|void The HTML markup if $echo is set to false. Void otherwise.
	 */
	public function settings_receipt($field, $echo = true)
	{
		// Prepare enabled field settings.
		$enabled_field = array(
			'name'       => $field['name'] . '_checkbox',
			'type'       => 'checkbox',
			'horizontal' => true,
			'choices'    => array(
				array(
					'label'    => esc_html__('Enabled', 'gravityformspaystack'),
					'name'     => $field['name'] . '_enabled',
					'value'    => "1",
					'default_value' => "1"
				),
			),
		);

		// Get checkbox markup.
		$html = $this->settings_checkbox($enabled_field, false);

		// Echo setting markup, if enabled.
		if ($echo) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Define the markup for the trial type field.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFAddOn::settings_checkbox()
	 *
	 * @param array     $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed. Defaults to true.
	 *
	 * @return string|void The HTML markup if $echo is set to false. Void otherwise.
	 */
	public function settings_trial($field, $echo = true)
	{
		// Prepare enabled field settings.
		$enabled_field = array(
			'name'       => $field['name'] . '_checkbox',
			'type'       => 'checkbox',
			'horizontal' => true,
			'choices'    => array(
				array(
					'label'    => esc_html__('Enabled', 'gravityformspaystack'),
					'name'     => $field['name'] . '_enabled',
					'value'    => '1',
					'onchange' => "if(jQuery(this).prop('checked')){
						jQuery('#gaddon-setting-row-trialPeriod').show('slow');
					} else {
						jQuery('#gaddon-setting-row-trialPeriod').hide('slow');
						jQuery('#trialPeriod').val( '' );
					}",
				),
			),
		);

		// Get checkbox markup.
		$html = $this->settings_checkbox($enabled_field, false);

		// Echo setting markup, if enabled.
		if ($echo) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Define the markup for the trial_period type field.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @uses GFAddOn::settings_text()
	 * @uses GFAddOn::field_failed_validation()
	 * @uses GFAddOn::get_error_icon()
	 *
	 * @param array     $field The field properties.
	 * @param bool|true $echo  Should the setting markup be echoed. Defaults to true.
	 *
	 * @return string|void The HTML markup if $echo is set to false. Void otherwise.
	 */
	public function settings_trial_period($field, $echo = true)
	{
		// Get text input markup.
		$html = $this->settings_text($field, false);

		// Prepare validation placeholder name.
		$validation_placeholder = array('name' => 'trialValidationPlaceholder');

		// Add validation indicator.
		if ($this->field_failed_validation($validation_placeholder)) {
			$html .= '&nbsp;' . $this->get_error_icon($validation_placeholder);
		}

		// If trial is not enabled and setup fee is enabled, hide field.
		$html .= '
			<script type="text/javascript">
			if( ! jQuery( "#trial_enabled" ).is( ":checked" ) || jQuery( "#setupFee_enabled" ).is( ":checked" ) ) {
				jQuery( "#trial_enabled" ).prop( "checked", false );
				jQuery( "#gaddon-setting-row-trialPeriod" ).hide();
			}
			</script>';

		// Echo setting markup, if enabled.
		if ($echo) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Prepare fields for field mapping in feed settings.
	 *
	 *
	 * @return array $fields
	 */
	public function billing_info_fields()
	{
		$fields = array(
			array(
				'name'       => 'address_line1',
				'label'      => __('Address', 'gravityformsconstantcontact'),
				'required'   => false,
				'field_type' => array('address'),
			),
			array(
				'name'       => 'address_line2',
				'label'      => __('Address 2', 'gravityformsconstantcontact'),
				'required'   => false,
				'field_type' => array('address'),
			),
			array(
				'name'       => 'address_city',
				'label'      => __('City', 'gravityformsconstantcontact'),
				'required'   => false,
				'field_type' => array('address'),
			),
			array(
				'name'       => 'address_state',
				'label'      => __('State', 'gravityformsconstantcontact'),
				'required'   => false,
				'field_type' => array('address'),
			),
			array(
				'name'       => 'address_zip',
				'label'      => __('Zip', 'gravityformsconstantcontact'),
				'required'   => false,
				'field_type' => array('address'),
			),
			array(
				'name'       => 'address_country',
				'label'      => __('Country', 'gravityformsconstantcontact'),
				'required'   => false,
				'field_type' => array('address'),
			),
		);

		return $fields;
	}

	/**
	 * Validate the custom_meta type field.
	 *
	 * @access public
	 *
	 * @uses GFAddOn::get_posted_settings()
	 * @uses GFAddOn::set_field_error()
	 *
	 * @param array $field The field properties. Not used.
	 *
	 * @return void
	 */
	public function validate_custom_meta($field)
	{
		/*
		 * Number of keys is limited to 20.
		 * Interface should control this, validating just in case.
		 * Key names have maximum length of 40 characters.
		 */

		// Get metadata from posted settings.
		$settings  = $this->get_posted_settings();
		$meta_data = $settings['metaData'];

		// If metadata is not defined, return.
		if (empty($meta_data)) {
			return;
		}

		// Get number of metadata items.
		$meta_count = count($meta_data);

		// If there are more than 20 metadata keys, set field error.
		if ($meta_count > 20) {
			$this->set_field_error(array(esc_html__('You may only have 20 custom keys.'), 'gravityformspaystack'));
			return;
		}

		// Loop through metadata and check the key name length (custom_key).
		foreach ($meta_data as $meta) {
			if (empty($meta['custom_key']) && !empty($meta['value'])) {
				$this->set_field_error(array('name' => 'metaData'), esc_html__("A field has been mapped to a custom key without a name. Please enter a name for the custom key, remove the metadata item, or return the corresponding drop down to 'Select a Field'.", 'gravityformspaystack'));
				break;
			} else if (strlen($meta['custom_key']) > 40) {
				$this->set_field_error(array('name' => 'metaData'), sprintf(esc_html__('The name of custom key %s is too long. Please shorten this to 40 characters or less.', 'gravityformspaystack'), $meta['custom_key']));
				break;
			}
		}
	}

	/**
	 * Prevent the 'options' checkboxes setting being included on the feed.
	 *
	 * @access public
	 *
	 * @used-by GFPaymentAddOn::other_settings_fields()
	 *
	 * @return false
	 */
	public function option_choices()
	{
		return false;
	}

	/**
	 * Add supported notification events.
	 *
	 * @access public
	 *
	 * @used-by GFFeedAddOn::notification_events()
	 * @uses    GFFeedAddOn::has_feed()
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return array|false The supported notification events. False if feed cannot be found within $form.
	 */
	public function supported_notification_events($form)
	{
		// If this form does not have a Paystack feed, return false.
		if (!$this->has_feed($form['id'])) {
			return false;
		}

		// Return Paystack notification events.
		return array(
			'complete_payment'          => esc_html__('Payment Completed', 'gravityformspaystack'),
			'refund_payment'            => esc_html__('Payment Refunded', 'gravityformspaystack'),
			'fail_payment'              => esc_html__('Payment Failed', 'gravityformspaystack'),
			'create_subscription'       => esc_html__('Subscription Created', 'gravityformspaystack'),
			'cancel_subscription'       => esc_html__('Subscription Canceled', 'gravityformspaystack'),
			'add_subscription_payment'  => esc_html__('Subscription Payment Added', 'gravityformspaystack'),
			'fail_subscription_payment' => esc_html__('Subscription Payment Failed', 'gravityformspaystack'),
		);
	}

	// # PAYSTACK TRANSACTIONS -------------------------------------------------------------------------------------------

	/**
	 * Useful when developing a payment gateway that processes the payment outside of the website (i.e. Paystack Redirect).
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFPaymentAddOn::entry_post_save()
	 *
	 * @param array $feed            Active payment feed containing all the configuration data.
	 * @param array $submission_data Contains form field data submitted by the user as well as payment information (i.e. payment amount, setup fee, line items, etc...).
	 * @param array $form            Current form array containing all form settings.
	 * @param array $entry           Current entry array containing entry information (i.e data submitted by users).
	 *
	 * @return void|string Return a full URL (including http:// or https://) to the payment processor.
	 */
	public function redirect_url($feed, $submission_data, $form, $entry)
	{
		// Don't process redirect url if request is a Paystack return
		if (!rgempty('gf_paystack_return', $_GET)) {
			return false;
		}

		// Updating lead's payment_status to Processing
		GFAPI::update_entry_property($entry['id'], 'payment_status', 'Processing');

		// Getting mode (Live (Production) or Test (Sandbox))
		$mode = $feed['meta']['mode'];

		// Setup the Paystack API
		$this->paystack_api($mode);

		// Getting the product status
		$is_product = $feed['meta']['transactionType'] === 'product';

		// Getting the subscription status
		$is_subscription = $feed['meta']['transactionType'] === 'subscription';

		$payment_amount = rgar($submission_data, 'payment_amount');
		$setup_fee      = rgar($submission_data, 'setup_fee');
		$trial_amount   = rgar($submission_data, 'trial');
		$line_items     = rgar($submission_data, 'line_items');
		$discounts      = rgar($submission_data, 'discounts');

		// Currency
		$currency = rgar($entry, 'currency');

		// Customer Info
		$customer_info = $this->get_fields_meta_data($feed, $entry, $this->get_customer_fields());

		// Get feed custom metadata
		$metadata = $this->get_paystack_meta_data($feed, $entry, $form);

		// URL that will listen to callback from Paystack
		$page_url = get_bloginfo('url');
		$ids_query = "ids={$entry['id']}|{$feed['id']}|{$form['id']}";
		$ids_query .= '&hash=' . wp_hash($ids_query);

		$return_url = add_query_arg('gf_paystack_return', base64_encode($ids_query), $page_url);

		// Generate transaction id
		$transaction_id = uniqid("gf-{$entry['id']}-");

		if ($is_product) {
			// Create a Payment Request & Send Invoice
		}

		// Initialize the charge on Paystack's servers - this will be used to charge the user's card
		$args = array(
			'email'        => $customer_info['email'],
			'currency'     => $currency,
			'amount'       => $this->get_amount_export($payment_amount, $currency),
			'reference'    => $transaction_id,
			'callback_url' => $return_url,
			'description'  => sprintf(__('%s (transaction: %s)', 'paystack'), $feed['meta']['feedName'], $transaction_id),
			'metadata'     => array_merge(array(
				'entry_id'    => $entry['id'],
				'site_url'    => esc_url(get_site_url()),
				'ip_address'  => $_SERVER['REMOTE_ADDR']
			), $metadata)
		);

		if ($is_subscription) {
			$plan = $this->create_plan($feed, $payment_amount, $currency);

			$args['plan']     = $plan['plan_code'];
			$args['channels'] = ['card'];
			
			gform_update_meta($entry['id'], 'paystack_plan_code', $plan['plan_code']);
		}

		// Initialize a new payment here
		$response = (object) $this->paystack_api->send_request("transaction/initialize/", $args);

		if (!$response->status) {
			return false;
		}

		gform_update_meta($entry['id'], 'paystack_tx_reference', $plan['reference']);
		gform_update_meta($entry['id'], 'paystack_tx_access_code', $plan['access_code']);

		$url = $response->data['authorization_url'];

		$this->log_debug(__METHOD__ . "(): Sending to Paystack: {$url}");

		return $url;
	}

	public function get_fields_meta_data($feed, $entry, $fields)
	{
		$data = [];

		foreach ($fields as $field) {
			$field_id = $feed['meta'][$field['meta_name']];
			$value    = rgar($entry, $field_id);

			if ($field['name'] == 'country') {
				$value = class_exists('GF_Field_Address') ? GF_Fields::get('address')->get_country_code($value) : GFCommon::get_country_code($value);
			} elseif ($field['name'] == 'state') {
				$value = class_exists('GF_Field_Address') ? GF_Fields::get('address')->get_us_state_code($value) : GFCommon::get_us_state_code($value);
			}

			if (!empty($value)) {
				$data["{$field['name']}"] = $value;
			}
		}

		return $data;
	}

	public function get_customer_fields()
	{
		return array(
			array('name' => 'email', 'label' => 'Email', 'meta_name' => 'customerInformation_email'),
			array('name' => 'first_name', 'label' => 'First Name', 'meta_name' => 'customerInformation_firstName'),
			array('name' => 'last_name', 'label' => 'Last Name', 'meta_name' => 'customerInformation_lastName'),
		);
	}

	public function get_billing_fields()
	{
		return array(
			array('name' => 'address1', 'label' => 'Address', 'meta_name' => 'billingInformation_address'),
			array('name' => 'address2', 'label' => 'Address 2', 'meta_name' => 'billingInformation_address2'),
			array('name' => 'city', 'label' => 'City', 'meta_name' => 'billingInformation_city'),
			array('name' => 'state', 'label' => 'State', 'meta_name' => 'billingInformation_state'),
			array('name' => 'zip', 'label' => 'Zip', 'meta_name' => 'billingInformation_zip'),
			array('name' => 'country', 'label' => 'Country', 'meta_name' => 'billingInformation_country'),
		);
	}

	/**
	 * Handle cancelling the subscription from the entry detail page.
	 *
	 * @param array $entry The entry object currently being processed.
	 * @param array $feed  The feed object currently being processed.
	 *
	 * @return bool True if successful. False if failed.
	 */
	public function cancel($entry, $feed)
	{
		// Getting mode (Live (Production) or Test (Sandbox))
		$mode = $feed['meta']['mode'];

		// Setup the Paystack API
		$this->paystack_api($mode);

		if (empty($entry['transaction_id'])) {
			return false;
		}

		// Get Paystack subscription object.
		$subscription = $this->get_subscription($entry['subscription_id']);

		if (!$subscription['status']) {
			return false;
		}

		if ($subscription['status'] !== 'active') {
			$this->log_debug(__METHOD__ . '(): Subscription already cancelled.');

			return true;
		}

		try {
			$this->paystack_api->send_request('subscription/disable', [
				'code'  => $entry['subscription_id'],
				'token' =>  null //email token,
			]);

			$this->log_debug(__METHOD__ . '(): Subscription cancelled.');

			return true;
		} catch (\Exception $e) {
			// Log error.
			$this->log_error(sprintf('%s(): Unable to cancel subscription; %s', __METHOD__, $e->getMessage()));

			return false;
		}
	}

	/**
	 * Display the thank you page when there's a gf_paystack_return URL param and the charge is successful.
	 *
	 * @since 1.0
	 */
	public function maybe_thankyou_page()
	{
		if (!$this->is_gravityforms_supported()) {
			return;
		}

		if ($str = sanitize_text_field(rgget('gf_paystack_return'))) {
			$str = base64_decode($str);

			parse_str($str, $query);

			if (wp_hash('ids=' . $query['ids']) == $query['hash']) {
				list($entry_id, $feed_id, $form_id) = explode('|', $query['ids']);

				$entry = GFAPI::get_entry($entry_id);
				$feed = $this->get_feed($feed_id);
				$form = GFAPI::get_form($form_id);

				if (is_wp_error($entry) || !$entry) {
					return;
				}

				// Getting mode (Live (Production) or Test (Sandbox))
				$mode = $feed['meta']['mode'];

				$this->paystack_api($mode);

				$reference = sanitize_text_field(rgget('reference'));

				$response = (object) $this->paystack_api->send_request("transaction/verify/{$reference}", [], 'get');

				$charge = (object) $response->data;

				if (!$response || $charge->status == 'failed') {
					// Charge Failed
					return;
				}

				// Log Payment successful payment from this addon to paystack
				$this->paystack_api->log_transaction_success($reference);

				// // Check if the webhook event has completed session, if not, call complete_checkout_session().
				if (!empty($charge->plan)) {
					$is_payment_completed = $entry['payment_status'] === 'Active';
				} else {
					$is_payment_completed = $entry['payment_status'] === 'Paid' || $entry['payment_status'] === 'Authorized';
				}

				if (!$is_payment_completed) {
					$this->log_debug(__METHOD__ . '(): Paystack Checkout will be completed in the form confirmation page.');
					// $this->complete_checkout_process($charge, $entry, $feed, $form);
				}

				if (!class_exists('GFFormDisplay')) {
					require_once(GFCommon::get_base_path() . '/form_display.php');
				}

				$confirmation = GFFormDisplay::handle_confirmation($form, $entry, false);

				if (is_array($confirmation) && isset($confirmation['redirect'])) {
					header("Location: {$confirmation['redirect']}");
					exit;
				}

				GFFormDisplay::$submission[$form_id] = array(
					'is_confirmation'      => true,
					'confirmation_message' => $confirmation,
					'form'                 => $form,
					'lead'                 => $entry,
				);
			}
		}
	}

	// # WEBHOOKS ------------------------------------------------------------------------------------------------------

	/**
	 * If the Paystack callback or webhook belongs to a valid entry process the raw response into a standard Gravity Forms $action.
	 *
	 * @access public
	 *
	 * @uses GFAddOn::get_plugin_settings()
	 * @uses GFPaystack::get_api_mode()
	 * @uses GFAddOn::log_error()
	 * @uses GFAddOn::log_debug()
	 * @uses GFPaymentAddOn::get_entry_by_transaction_id()
	 * @uses GFPaymentAddOn::get_amount_import()
	 * @uses GFPaystack::get_subscription_line_item()
	 * @uses GFPaystack::get_captured_payment_note()
	 * @uses GFAPI::get_entry()
	 * @uses GFCommon::to_money()
	 *
	 * @return array|bool|WP_Error Return a valid GF $action or if the webhook can't be processed a WP_Error object or false.
	 */
	public function callback()
	{
		if (!$this->is_gravityforms_supported()) {
			return;
		}
	
		$this->log_debug(__METHOD__ . '(): Webhook request received. Starting to process => ' . print_r($_REQUEST, true));

		// Retrieve the request's payload
		$request = @file_get_contents('php://input');

		// Validate Webhook Request
		$is_verified = $this->paystack_api->validate_webhook($request);
		if (!$is_verified) {
			$this->log_error(__METHOD__ . '(): Wehhook request is invalid. Aborting.');

			return false;
		}

		// Get request properties.
		$body =  json_decode($request, true);
		$data = $body['data'];
		$event = $body['event'];

		// Get api mode from data
		$mode = $data['domain'];

		$this->paystack_api($mode);

		$this->log_debug(__METHOD__ . '() Webhook event details => ' . print_r($data, 1));

		switch ($event) {
			case 'charge.success':
				$entry_id = rgars($data, 'metadata/entry_id');
				$entry = GFAPI::get_entry($entry_id);
				
				$this->log_debug(__METHOD__ . '(): Entry has been found => ' . print_r($entry, true));

				// $feed = $this->get_payment_feed($entry);
				
				// $this->log_debug(__METHOD__ . "(): Form {$entry['form_id']} is properly configured.");
		
				$action['entry_id'] = $entry_id;
				$action['type']     = 'complete_payment';
				$action['amount']   = $this->get_amount_import(rgar($data, 'amount'), $data['currency']);

				break;

			case 'paymentrequest.success':

				break;

			case 'subscription.create':

				break;

			case 'invoice.create || invoice.update':

				break;
		}

		// if (rgempty('entry_id', $action)) {
		// 	$this->log_debug(__METHOD__ . '() entry_id not set for callback action; no further processing required.');

		// 	return false;
		// }

		return $action;
	}

	/**
	 * Generate the url Paystack webhooks should be sent to.
	 *
	 * @access public
	 *
	 * @used-by GFPaystack::get_webhooks_section_description()
	 *
	 * @param int $feed_id The feed id.
	 *
	 * @return string The webhook URL.
	 */
	public function get_webhook_url($feed_id = null)
	{
		$url = home_url('/', 'https') . '?callback=' . $this->_slug;

		if (!rgblank($feed_id)) {
			$url .= '&fid=' . $feed_id;
		}

		return $url;
	}

	/**
	 * Helper to check that webhooks are enabled.
	 *
	 * @access public
	 *
	 * @used-by GFPaystack::can_create_feed()
	 * @uses    GFAddOn::get_plugin_setting()
	 *
	 * @return bool True if webhook is enabled. False otherwise.
	 */
	public function is_webhook_enabled()
	{
		return $this->get_plugin_setting('webhooks_enabled') == true;
	}

	// # PAYSTACK HELPER FUNCTIONS ---------------------------------------------------------------------------------------

	/**
	 * Try and retrieve the plan if a plan with the matching id has previously been created.
	 *
	 * @access public
	 *
	 * @param string $plan_id The subscription plan id or code.
	 *
	 * @return array|bool|string $plan The plan details. False if not found. If invalid request, the error message.
	 */
	public function get_plan($plan_id_or_code)
	{
		// Get Paystack plan.
		$response = (object) $this->paystack_api->send_request("plan/{$plan_id_or_code}");

		$plan = $response->data;

		return $plan;
	}

	/**
	 * Create and return a Paystack plan with the specified properties.
	 *
	 * @access  public
	 *
	 * @uses    GFPaymentAddOn::get_amount_export()
	 * @uses    GFAddOn::log_debug()
	 *
	 * @param array     $feed              The feed currently being processed.
	 * @param float|int $payment_amount    The recurring amount.
	 * @param int       $trial_period_days The number of days the trial should last.
	 * @param string    $currency          The currency code for the entry being processed.
	 *
	 * @return array The plan object.
	 */
	public function create_plan($feed, $payment_amount, $currency)
	{
		// Prepare plan metadata.
		$name = rgars($feed, 'meta/plan_name') ?? $feed['meta']['feedName'];

		$billing_cycle   = rgar($feed['meta'], 'billingCycle_unit');
		$recurring_times = (int) rgar($feed['meta'], 'recurringTimes');

		$send_invoices   = (rgar($feed['meta'], 'send_invoices_enabled') == 1) ? true : false;

		$args = array(
			'name'            => $name,
			'interval'        => $billing_cycle,
			'invoice_limit'   => $recurring_times,
			'currency'        => $currency,
			'send_invoices'   => $send_invoices,
			'amount'          => $this->get_amount_export($payment_amount, $currency)
		);

		// Log the plan to be created.
		$this->log_debug(__METHOD__ . '(): Plan to be created => ' . print_r($args, 1));

		// Create Paystack plan.
		$response = (object) $this->paystack_api->send_request('plan', $args);

		$plan = $response->data;

		return $plan;
	}

	/**
	 * Gets the Paystack subscription object for the given ID.
	 *
	 * @param string $subscription_id The subscription ID.
	 *
	 * @return bool|array
	 */
	public function get_subscription($subscription_id_or_code)
	{
		$this->log_debug(__METHOD__ . '(): Getting subscription ' . $subscription_id_or_code);

		try {
			$subscription = $this->paystack_api->send_request("subscription/{$subscription_id_or_code}");
		} catch (\Exception $e) {
			$this->log_error(__METHOD__ . '(): Unable to get subscription; ' . $e->getMessage());
			$subscription = false;
		}

		return $subscription;
	}

	/**
	 * If custom meta data has been configured on the feed retrieve the mapped field values.
	 *
	 * @access public
	 *
	 * @uses  GFAddOn::get_field_value()
	 *
	 * @param array $feed  The feed object currently being processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form  The form object currently being processed.
	 *
	 * @return array The Paystack meta data.
	 */
	public function get_paystack_meta_data($feed, $entry, $form)
	{
		// Initialize metadata array.
		$metadata = array();

		// Find feed metadata.
		$custom_meta = rgars($feed, 'meta/metaData');

		if (is_array($custom_meta)) {
			// Loop through custom meta and add to metadata for paystack.
			foreach ($custom_meta as $meta) {

				// If custom key or value are empty, skip meta.
				if (empty($meta['custom_key']) || empty($meta['value'])) {
					continue;
				}

				// Make the key available to the gform_paystack_field_value filter.
				$this->_current_meta_key = $meta['custom_key'];

				// Get field value for meta key.
				$field_value = $this->get_field_value($form, $entry, $meta['value']);

				if (!empty($field_value)) {
					// // Trim to 500 characters.
					// $field_value = substr($field_value, 0, 500);

					// Add to metadata array.
					$metadata[$meta['custom_key']] = $field_value;
				}
			}

			// Clear the key in case get_field_value() and gform_paystack_field_value are used elsewhere.
			$this->_current_meta_key = '';
		}

		return $metadata;
	}

	/**
	 * Initialize the Paystack API and returns the GF Paysack API Object.
	 *
	 * @access  public
	 *
	 * @used-by GFPaystack::cancel()
	 * @used-by GFPaystack::get_paystack_event()
	 * @used-by GFPaystack::subscribe()
	 * @uses    GFAddOn::get_base_path()
	 * @uses    GFPaystack::get_secret_api_key()
	 * @uses    GFPaystack::get_public_api_key()
	 * 
	 * @param null|string $mode The API mode; live or test.
	 * @param null|array  $settings The settings.
	 * 
	 * @return \GFPaystackApi
	 */
	public function paystack_api($mode = null, $settings = null)
	{
		if (empty($settings)) {
			$settings = $this->get_plugin_settings();
		}

		if (empty($mode)) {
			$mode  = $this->get_api_mode($settings);
		}

		$config = (object) array(
			'secret_key' => $this->get_secret_api_key($mode, $settings),
			'public_key' => $this->get_public_api_key($mode, $settings)
		);

		$this->log_debug(sprintf('%s(): Initializing Paystack API for %s mode.', __METHOD__, $mode));

		return $this->paystack_api = new GFPaystackApi($config);
	}

	// # OTHER HELPER FUNCTIONS ----------------------------------------------------------------------------------------------

	/**
	 * Retrieve the specified api key.
	 *
	 * @access  public
	 *
	 * @used-by GFPaystack::get_public_api_key()
	 * @used-by GFPaystack::get_secret_api_key()
	 * @uses    GFPaystack::get_query_string_api_key()
	 * @uses    GFAddOn::get_plugin_settings()
	 * @uses    GFPaystack::get_api_mode()
	 * @uses    GFAddOn::get_setting()
	 *
	 * @param string      $type    The type of key to retrieve.
	 * @param null|string $mode    The API mode; live or test.
	 * @param null|int    $settings The current settings.
	 *
	 * @return string
	 */
	public function get_api_key($type = 'secret', $mode = null, $settings = null)
	{
		// Check for api key in query first; user must be an administrator to use this feature.
		$api_key = $this->get_query_string_api_key($type);
		if ($api_key && current_user_can('update_core')) {
			return $api_key;
		}

		if (!isset($settings)) {
			$settings = $this->get_plugin_settings();

			if (!$mode) {
				// Get API mode.
				$mode = $this->get_api_mode($settings);
			}
		}

		// Get API key based on current mode and defined type.
		$setting_key = "{$mode}_{$type}_key";
		$api_key     = $this->get_setting($setting_key, '', $settings);

		return $api_key;
	}

	/**
	 * Helper to implement the gform_paystack_api_mode filter so the api mode can be overridden.
	 *
	 * @access public
	 *
	 * @used-by GFPaystack::get_api_key()
	 * @used-by GFPaystack::callback()
	 * @used-by GFPaystack::can_create_feed()
	 *
	 * @param array $settings The plugin settings.
	 * @param int   $feed_id  Feed ID.
	 *
	 * @return string $api_mode Either live or test.
	 */
	public function get_api_mode($settings, $feed_id = null)
	{
		// Get API mode from settings.
		$api_mode = rgar($settings, 'api_mode');

		/**
		 * Filters the API mode.
		 *
		 * @since 1.10.1
		 * @since 2.8   Added $feed_id param.
		 *
		 * @param string $api_mode The API mode.
		 * @param int    $feed_id  Feed ID.
		 */
		return apply_filters('gform_paystack_api_mode', $api_mode, $feed_id);
	}

	/**
	 * Retrieve the specified api key from the query string.
	 *
	 * @access public
	 *
	 * @used-by GFPaystack::get_api_key()
	 *
	 * @param string $type The type of key to retrieve. Defaults to 'secret'.
	 *
	 * @return string The result of the query string.
	 */
	public function get_query_string_api_key($type)
	{
		return rgget($type);
	}

	/**
	 * Retrieve the public api key.
	 *
	 * @access  public
	 *
	 * @uses  GFPaystack::get_api_key()
	 *
	 * @param null|string $mode    The API mode; live or test.
	 * @param array|null $settings The current settings.
	 *
	 * @return string The public API key.
	 */
	public function get_public_api_key($mode = null, $settings = null)
	{
		if (empty($settings)) {
			$settings = $this->get_plugin_settings();
		}

		if (empty($mode)) {
			$mode = $this->get_api_mode($settings);
		}

		return $this->get_api_key('public', $mode, $settings);
	}

	/**
	 * Retrieve the secret api key.
	 *
	 * @access  public
	 *
	 * @used-by GFPaystack::include_paystack_api()
	 * @uses    GFPaystack::get_api_key()
	 *
	 * @param null|string $mode    The API mode; live or test.
	 * @param null|array  $settings The current settings.
	 *
	 * @return string The secret API key.
	 */
	public function get_secret_api_key($mode = null, $settings = null)
	{
		if (empty($settings)) {
			$settings = $this->get_plugin_settings();
		}

		if (empty($mode)) {
			$mode = $this->get_api_mode($settings);
		}

		return $this->get_api_key('secret', $mode, $settings);
	}

	/**
	 * Adds the currency if it isn't already registered.
	 *
	 * @since   1.0
	 * @access  public
	 * @used-by gform_currencies
	 * 
	 * @param array $currencies The current currencies registered in Gravity Forms.
	 *
	 * @return array List of supported currencies.
	 */
	public function add_paystack_currencies($currencies)
	{
		// Check if the currency is already registered.
		if (!array_key_exists('NGN', $currencies)) {
			// Add NGN to the list of supported currencies.
			$currencies['NGN'] = array(
				'name'               => 'Nigeria Naira',
				'symbol_left'        => '&#8358;',
				'symbol_right'       => '',
				'symbol_padding'     => ' ',
				'thousand_separator' => ',',
				'decimal_separator'  => '.',
				'decimals'           => 2
			);
		}

		// Check if the currency is already registered.
		if (!array_key_exists('GHS', $currencies)) {
			// Add GHS to the list of supported currencies.
			$currencies['GHS'] = array(
				'name'               => 'Ghana Cedis',
				'symbol_left'        => '&#8373;',
				'symbol_right'       => '',
				'symbol_padding'     => ' ',
				'thousand_separator' => ',',
				'decimal_separator'  => '.',
				'decimals'           => 2
			);
		}

		return $currencies;
	}

	/**
	 * Check if rate limits is enabled.
	 *
	 *
	 * @param int $form_id The form ID.
	 *
	 * @return bool
	 */
	public function is_rate_limits_enabled($form_id)
	{
		/**
		 * Allow enabling or disable the rate limit check.
		 *
		 * @param bool $has_error The default is false.
		 * @param int  $form_id   The form ID.
		 */
		$this->_enable_rate_limits = apply_filters('gform_paystack_enable_rate_limits', $this->_enable_rate_limits, $form_id);

		return $this->_enable_rate_limits;
	}
}
