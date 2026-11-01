<?php
/**
 * Plugin Name: Cassette-CMF React Settings Example (Array)
 * Plugin URI: https://github.com/pedalcms/cassette-cmf
 * Description: A settings page drawn by the React renderer (render_mode => 'react'), using every field type
 * Version: 1.0.0
 * Author: PedalCMS
 * License: GPL v2 or later
 *
 * @package CassetteCMFReactSettings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use PedalCMS\CassetteCMF\CassetteCMF;

/**
 * =============================================================================
 * REACT SETTINGS EXAMPLE
 * =============================================================================
 *
 * One settings page (Settings > React Demo) with `render_mode => 'react'`. It
 * uses every field type, a repeater, a group, tabs inside a metabox, a
 * conditional field and a `show_in_rest => false` field (shown in the
 * "classic mode only" notice). Remove `render_mode` to see the same page in
 * the classic renderer.
 * =============================================================================
 */
function cassette_cmf_react_settings_init() {
	$cmf = CassetteCMF::init();

	$cmf->register_from_array(
		[
			'settings_pages' => [
				[
					'id'          => 'react-demo',
					'page_title'  => 'React Settings Demo',
					'menu_title'  => 'React Demo',
					'description' => 'Every field type, drawn with @wordpress/components and saved over REST.',
					'capability'  => 'manage_options',
					'parent_slug' => 'options-general.php',
					'render_mode' => 'react',
					'fields'      => [
						[
							'name'     => 'react_demo_box',
							'type'     => 'metabox',
							'label'    => 'Demo settings',
							'context'  => 'normal',
							'priority' => 'high',
							'fields'   => [
								[
									'name'        => 'react_demo_tabs',
									'type'        => 'tabs',
									'orientation' => 'horizontal',
									'tabs'        => [
										[
											'id'     => 'basics',
											'label'  => 'Basics',
											'fields' => [
												[
													'name' => 'site_name',
													'type' => 'text',
													'label' => 'Site name',
													'description' => 'Required.',
													'required' => true,
													'placeholder' => 'Acme Inc.',
												],
												[
													'name' => 'contact_email',
													'type' => 'email',
													'label' => 'Contact email',
												],
												[
													'name' => 'home_url',
													'type' => 'url',
													'label' => 'Home URL',
												],
												[
													'name' => 'retries',
													'type' => 'number',
													'label' => 'Retries',
													'description' => 'Between 1 and 10.',
													'min'  => 1,
													'max'  => 10,
													'default' => 3,
												],
												[
													'name' => 'launch_date',
													'type' => 'date',
													'label' => 'Launch date',
												],
												[
													'name' => 'brand_color',
													'type' => 'color',
													'label' => 'Brand color',
													'default' => '#2271b1',
												],
												[
													'name' => 'tagline',
													'type' => 'textarea',
													'label' => 'Tagline',
													'rows' => 3,
												],
											],
										],
										[
											'id'     => 'choices',
											'label'  => 'Choices',
											'fields' => [
												[
													'name' => 'mode',
													'type' => 'select',
													'label' => 'Mode',
													'options' => [
														'basic' => 'Basic',
														'pro'   => 'Pro',
													],
													'default' => 'basic',
												],
												[
													'name' => 'license_key_note',
													'type' => 'text',
													'label' => 'Pro note',
													'description' => 'Only shown in Pro mode.',
													'conditional' => [
														'field'    => 'mode',
														'operator' => '==',
														'value'    => 'pro',
													],
												],
												[
													'name' => 'regions',
													'type' => 'select',
													'label' => 'Regions',
													'multiple' => true,
													'options' => [
														'eu' => 'Europe',
														'na' => 'North America',
														'as' => 'Asia',
													],
												],
												[
													'name' => 'layout',
													'type' => 'radio',
													'label' => 'Layout',
													'options' => [
														'wide'   => 'Wide',
														'narrow' => 'Narrow',
													],
													'default' => 'wide',
												],
												[
													'name' => 'enable_cache',
													'type' => 'checkbox',
													'label' => 'Enable cache',
												],
												[
													'name' => 'channels',
													'type' => 'checkbox',
													'label' => 'Channels',
													'options' => [
														'email' => 'Email',
														'sms'   => 'SMS',
														'push'  => 'Push',
													],
												],
											],
										],
										[
											'id'     => 'content',
											'label'  => 'Content',
											'fields' => [
												[
													'name' => 'react_demo_intro',
													'type' => 'custom_html',
													'content' => '<p><strong>Heads up:</strong> the editor below is a plain textarea in the React renderer.</p>',
												],
												[
													'name' => 'logo',
													'type' => 'upload',
													'label' => 'Logo',
												],
												[
													'name' => 'about',
													'type' => 'wysiwyg',
													'label' => 'About',
												],
												[
													'name' => 'api_key',
													'type' => 'secret',
													'label' => 'API key',
													'description' => 'Write-only. Leave empty to keep the stored key.',
												],
												[
													'name' => 'admin_password',
													'type' => 'password',
													'label' => 'Admin password',
													'description' => 'Write-only. Leave empty to keep the stored password.',
												],
											],
										],
										[
											'id'     => 'lists',
											'label'  => 'Lists',
											'fields' => [
												[
													'name' => 'links',
													'type' => 'repeater',
													'label' => 'Links',
													'button_label' => 'Add link',
													'row_label' => 'Link {{index}}',
													'row_label_field' => 'link_title',
													'fields' => [
														[
															'name'  => 'link_title',
															'type'  => 'text',
															'label' => 'Title',
														],
														[
															'name'  => 'link_url',
															'type'  => 'url',
															'label' => 'URL',
														],
													],
												],
											],
										],
									],
								],
							],
						],
						[
							'name'   => 'react_demo_group',
							'type'   => 'group',
							'label'  => 'Advanced',
							'fields' => [
								[
									'name'  => 'debug_label',
									'type'  => 'text',
									'label' => 'Debug label',
								],
								[
									'name'         => 'classic_only_note',
									'type'         => 'text',
									'label'        => 'Classic-only note',
									'show_in_rest' => false,
								],
							],
						],
					],
				],
			],
		]
	);
}
add_action( 'init', 'cassette_cmf_react_settings_init' );
