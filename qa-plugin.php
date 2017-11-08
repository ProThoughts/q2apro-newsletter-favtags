<?php

/*
	Plugin Name: Q2APRO Newsletter FavTags
	Plugin URI: http://www.q2apro.com/plugins/newsletter
	Plugin Description: Users can subscribe to their favorite tags and the plugin emails them daily if there are new questions.
	Plugin Version: 1.0
	Plugin Date: 2016-10-07
	Plugin Author: q2apro.com
	Plugin Author URI: http://www.q2apro.com
	Plugin Minimum Question2Answer Version: 1.6
	Plugin Update Check URI:

	Licence: Copyright © q2apro.com - All rights reserved
*/

if(!defined('QA_VERSION'))
{
	header('Location: ../../');
	exit;
}

// page for manual mailing and display of newsletter output
qa_register_plugin_module('page', 'q2apro-favtagsmails-page.php', 'q2apro_favtagsmails_page', 'Favorite Tags Mails Page');

// page to list all users with their tag favorites
qa_register_plugin_module('page', 'q2apro-favtagsusers-page.php', 'q2apro_favtagsusers_page', 'Favorite Tags All Users Page');

// page for each user to change his newsletter tag settings
qa_register_plugin_module('page', 'q2apro-favtagsmails-usersettings.php', 'q2apro_favtagsmails_user_settings', 'FavTags User Settings');

// language files
qa_register_plugin_phrases('q2apro-favtagsmails-lang-*.php', 'q2apro_favtagsmails_lang');

// admin module
qa_register_plugin_module('module', 'q2apro-favtagsmails-admin.php', 'q2apro_favtagsmails_admin', 'Favorite Tags Mails Plugin');

// event module to add user to qa_favtags table
qa_register_plugin_module('event', 'q2apro-favtagsmails-updater.php', 'q2apro_favtagsmails_updater', 'Favorite Tags Updater');



// q2apro custom function
function q2apro_sendFavtagsNewsletter()
{
	// already sent today? this replaces a cronjob
	$date = date('Y-m-d');
	if($date != qa_opt('q2apro_favtagsmails_checkdate'))
	{
		qa_opt('q2apro_favtagsmails_checkdate', $date);

		require_once QA_INCLUDE_DIR.'qa-app-posts.php';
		require_once QA_INCLUDE_DIR.'qa-app-emails.php';

		// get all recent questions from within last 3 days
		$questions = qa_db_read_all_assoc(
							qa_db_query_sub('SELECT postid,tags,title FROM `^posts`
											WHERE `tags` != ""
											AND type="Q"
											AND DATE(`created`) > CURDATE() - INTERVAL 3 DAY
											') );
											// memo: twice listing can happen if tags are "nekilnojamas,turtas" which is found twice in "nekilnojamas-turtas"
		// holds all questions with <li> elements
		$nlQuestions = array();

		foreach($questions as $question)
		{
			// save questions in array
			if(isset($question['postid']))
			{
				$postid = $question['postid'];
				$qTitle = $question['title'];

				// explode tags string into array
				$tagsArr = explode(',', $question['tags']);

				// we use the tagid as the array index and add the questions as <li> to it
				foreach($tagsArr as $qTag)
				{
					$qu_url = qa_path_html(qa_q_request($postid, $qTitle), null, qa_opt('site_url'), null, null);
					/*$tagid = qa_db_read_one_value(
									qa_db_query_sub('SELECT wordid FROM `^words`
													WHERE word = #
													', $qTag), true);
													*/
					if(!isset($nlQuestions[$qTag]))
					{
						$nlQuestions[$qTag] = '<ol><li><a href="'.$qu_url.'">'.htmlspecialchars($qTitle).'</a></li>';
					}
					else
					{
						$nlQuestions[$qTag] .= ' <li><a href="'.$qu_url.'">'.htmlspecialchars($qTitle).'</a></li>';
					}
				}
			}
		}
		// close all list elements
		foreach($nlQuestions as $q => $val)
		{
			$nlQuestions[$q] = $val.'</ol>';
		}

		// BIG arrray that holds all data
		$userdata = array();
		$countmails = 0;

		// get all user favorites
		$queryFavoriteTags = qa_db_query_sub('SELECT userid, tagwords FROM `^favtags`');

		// get all tagids and assign them to userid
		while ( ($row = qa_db_read_one_assoc($queryFavoriteTags,true)) !== null )
		{
			$userdata[$row['userid']]['userid'] = $row['userid'];
			$userdata[$row['userid']]['tagwords'] = $row['tagwords'];
		}

		// assign questions from last 3 days to user, regarding the user's tagwords
		$questionFound = false;
		foreach($userdata as $user)
		{
			$userid = $user['userid'];
			$usertags = explode(',', $user['tagwords']);
			// receive questions for each tag (tag is key of associative array)
			foreach($usertags as $qtag)
			{
				// does question with tag exist
				if(isset($nlQuestions[$qtag]))
				{
					$questionFound = true;
					// save all questions to userid
					$userdata[$userid]['questions'][] = array(
						'quList' => $nlQuestions[$qtag],
						'tagword' => $qtag,
					);
				}
			}
		}
		if(!$questionFound)
		{
			return;
		}

		// assemble email body
		foreach($userdata as $user)
		{
			$emailbody = '';
			$userid = $user['userid'];
			// var_dump($user);

			$recentTagWord = '';
			$questionList = '';
			$nquestion = 0;
			if(isset($userdata[$user['userid']]['questions']))
			{
				// get number of questions
				$nquestion = count($userdata[$user['userid']]['questions']);
				// go over all questions (hold quList and tagword)
				foreach($userdata[$user['userid']]['questions'] as $quwithtag)
				{
					if(isset($quwithtag['tagword']))
					{
						$questionList .= '<h3 style="margin-left:20px;text-transform:uppercase;">
							<a href="'.qa_opt('site_url').'tag/'.$quwithtag['tagword'].'" style="color:#555;">'.$quwithtag['tagword'].'</a>
						</h3>';
						$questionList .= $quwithtag['quList'];
					}
				}
			}

			// get userdata
			$userinfo = qa_db_select_with_pending(qa_db_user_account_selectspec($userid, true));

			$emailbody .= '
				<p>
					'.qa_lang('q2apro_favtagsmails_lang/mail_hello').' '.$userinfo['handle'].',
				</p>
				<p>
					'.qa_lang('q2apro_favtagsmails_lang/mail_intro').'
				</p>
				'.$questionList.'
				<p>
					'.qa_lang('q2apro_favtagsmails_lang/mail_greeting').'<br />'.qa_lang('q2apro_favtagsmails_lang/mail_greetername').'
				</p>
				<p>&nbsp;</p>
			';
			// unsubscribe link
			$emailbody .= '
				<p style="font-size:12px;color:#999;">
					'.strtr( qa_lang('q2apro_favtagsmails_lang/mail_subscribehint'), array(
						'^1' => '<a target="_blank" style="color:#999 !important;text-decoration:underline;" href="'.qa_opt('site_url').'newsletter?userid='.$userid.'">',
						'^2' => '</a>'
						)).'
				</p>
			';

			if($nquestion>0)
			{
				$countmails++;
			}

			if($nquestion>0 && !($userinfo['flags'] & QA_USER_FLAGS_NO_MAILINGS))
			{
				qa_send_email(array(
					'fromemail' => qa_opt('mailing_from_email'),
					'fromname' => qa_opt('mailing_from_name'),
					'toemail' => $userinfo['email'],
					'toname' => $userinfo['handle'],
					'subject' => qa_lang('q2apro_favtagsmails_lang/mail_subject'),
					'body' => trim($emailbody),
					'html' => true,
				));
			}
		} // END foreach($userdata as $user)
	} // END if($date != qa_opt('q2apro_favtagsmails_checkdate'))
} // END function q2apro_sendFavtagsNewsletter()


/*
	Omit PHP closing tag to help avoid accidental output
*/
