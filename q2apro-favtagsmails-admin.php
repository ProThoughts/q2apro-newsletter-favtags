<?php

/*
	Plugin Name: Newsletter FavTags
	Plugin URI: http://www.q2apro.com/plugins/newsletter
*/

	class q2apro_favtagsmails_admin
	{

		function init_queries($tableslc)
		{
			//
		}

		// option's value is requested but the option has not yet been set
		function option_default($option)
		{
			switch($option)
			{
				case 'q2apro_favtagsmails_enabled':
					return 1; // true
				case 'q2apro_favtagsmails_permission':
					return QA_PERMIT_ADMINS; // default level to access the page
				case 'q2apro_favtagsmails_predefined_tags':
					return '';
				case 'q2apro_favtagsmails_checkdate':
					return date('Y-m-d');
				default:
					return null;
			}
		}

		function allow_template($template)
		{
			return ($template!='admin');
		}

		function admin_form(&$qa_content)
		{

			// process the admin form if admin hit Save-Changes-button
			$ok = null;
			if (qa_clicked('q2apro_favtagsmails_save'))
			{
				qa_opt('q2apro_favtagsmails_enabled', (bool)qa_post_text('q2apro_favtagsmails_enabled')); // empty or 1
				qa_opt('q2apro_favtagsmails_permission', (int)qa_post_text('q2apro_favtagsmails_permission')); // level
				qa_opt('q2apro_favtagsmails_predefined_tags', (String)qa_post_text('q2apro_favtagsmails_predefined_tags'));
				$ok = qa_lang('admin/options_saved');
			}

			// form fields to display frontend for admin
			$fields = array();

			$fields[] = array(
				'type' => 'checkbox',
				'label' => qa_lang('q2apro_favtagsmails_lang/enable_plugin'),
				'tags' => 'name="q2apro_favtagsmails_enabled"',
				'value' => qa_opt('q2apro_favtagsmails_enabled'),
			);

			$view_permission = (int)qa_opt('q2apro_favtagsmails_permission');
			$permitoptions = qa_admin_permit_options(QA_PERMIT_ALL, QA_PERMIT_SUPERS, false, false);

			$fields[] = array(
				'type' => 'static',
				'note' => 'Preview-Page for the Newsletter: <a target="_blank" href="'.qa_opt('site_url').'favtagsmails'.'">'.qa_opt('site_url').'favtagsmails'.'</a>',
			);
			$fields[] = array(
				'type' => 'static',
				'note' => 'Manage Newsletter subscriptions of users: <a target="_blank" href="'.qa_opt('site_url').'favtagsusers'.'">'.qa_opt('site_url').'favtagsusers'.'</a>',
			);
			$fields[] = array(
				'type' => 'select',
				'label' => qa_lang('q2apro_favtagsmails_lang/minimum_level'),
				'tags' => 'name="q2apro_favtagsmails_permission"',
				'options' => $permitoptions,
				'value' => $permitoptions[$view_permission],
			);

			$fields[] = array(
				'type' => 'input',
				'label' => qa_lang('q2apro_favtagsmails_lang/predefined_tags'),
				'tags' => 'name="q2apro_favtagsmails_predefined_tags"',
				'value' => qa_opt('q2apro_favtagsmails_predefined_tags'),
			);

			$fields[] = array(
				'type' => 'static',
				'note' => '<span style="font-size:75%;color:#789;">'.strtr( qa_lang('q2apro_favtagsmails_lang/contact'), array(
							'^1' => '<a target="_blank" href="http://www.q2apro.com/plugins/favtagsmails">',
							'^2' => '</a>'
						  )).'</span>',
			);

			$fields[] = array(
				'type' => 'static',
				'note' => '<p style="color:#55F;">'.qa_lang('q2apro_favtagsmails_lang/mail_last_sent').' '.qa_opt('q2apro_favtagsmails_checkdate').'</p>',
			);

			return array(
				'ok' => ($ok && !isset($error)) ? $ok : null,
				'fields' => $fields,
				'buttons' => array(
					array(
						'label' => qa_lang_html('main/save_button'),
						'tags' => 'name="q2apro_favtagsmails_save"',
					),
				),
			);

		} // END function admin_form(&$qa_content)
	} // END class q2apro_favtagsmails_admin


/*
	Omit PHP closing tag to help avoid accidental output
*/
