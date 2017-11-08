<?php

/*
	Plugin Name: Newsletter FavTags
	Plugin URI: http://www.q2apro.com/plugins/newsletter
*/

if (!defined('QA_VERSION'))
{ // don't allow this page to be requested directly from browser
	header('Location: ../');
	exit;
}

class q2apro_favtagsmails_updater
{

	function init_queries($tableslc)
	{
		// none
	}

	function option_default($option)
	{
		// none
	}

	function process_event($event, $userid, $handle, $cookieid, $params)
	{
		if(qa_opt('q2apro_favtagsmails_enabled')==1)
		{
			// do the newsletter sending on Q or A because user will accept to wait 1-2 seconds to have his answer posted ;-)
			// post on WED (3) and SAT (6)
			if(date('N', time()) == 3 || date('N', time()) == 6)
			{
				if($event == 'q_post' || $event == 'a_post') {
					// send newsletter to all members
					q2apro_sendFavtagsNewsletter();
				}
			}

			// when new user registers, we can subscribe him to the newsletter with a predefined tag
			if($event == 'u_register')
			{
				// add userid with predefined tags into qa_favtag table
				$prefavtags = qa_opt('q2apro_favtagsmails_predefined_tags');
				if(!empty($prefavtags))
				{
					// get userid from email
					$userid = qa_db_read_one_value(
											qa_db_query_sub('SELECT userid FROM `^users`
														  WHERE email = #
														 ', $params['email']), true);

					qa_db_query_sub('INSERT IGNORE INTO `^favtags`
										SET userid = #,
										tagwords = #
										', $userid, $prefavtags);
				}
			} // end u_register
		} // end if enabled
	} // end function process_event

} // end class q2apro_favtagsmails_updater


/*
	Omit PHP closing tag to help avoid accidental output
*/
