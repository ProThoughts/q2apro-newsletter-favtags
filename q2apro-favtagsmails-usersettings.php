<?php

/*
	Plugin Name: Newsletter FavTags
	Plugin URI: http://www.q2apro.com/plugins/newsletter
*/

	class q2apro_favtagsmails_user_settings
	{

		var $directory;
		var $urltoroot;

		function load_module($directory, $urltoroot)
		{
			$this->directory=$directory;
			$this->urltoroot=$urltoroot;
		}

		// for display in admin interface under admin/pages
		function suggest_requests()
		{
			return array(
				array(
					'title' => 'FavTags User Settings', // title of page
					'request' => 'FavTags User Settings', // request name
					'nav' => 'M', // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
				),
			);
		}

		// for url query
		function match_request($request)
		{
			if($request=='newsletter')
			{
				return true;
			}

			return false;
		}

		function process_request($request)
		{

			if(qa_opt('q2apro_favtagsmails_enabled')!=1)
			{
				$qa_content = qa_content_prepare();
				$qa_content['error'] = '<div>'.qa_lang_html('q2apro_favtagsmails_lang/plugin_disabled').'</div>';
				return $qa_content;
			}

			// get userid from login
			$userid = qa_get_logged_in_userid();
			if(!isset($userid))
			{
				$qa_content = qa_content_prepare();
				$qa_content['error'] = '<div>Jei norite naudoti šį puslapį, prisijunkite.</div>'; // Please login to access this page.
				return $qa_content;
			}

			// process new tags
			require_once QA_INCLUDE_DIR.'qa-app-posts.php';

			// get username
			$userhandle = qa_post_userid_to_handle($userid);

			// AJAX post: user wants to add or remove a favorite tag
			$transferString = qa_post_text('ajaxdata'); // holds the tag
			if(isset($transferString) && $transferString!='')
			{
				// to smaller letters
				$transferString = strtolower($transferString);

				$newdata = json_decode($transferString, true);
				$newdata = str_replace('&quot;', '"', $newdata); // see stackoverflow.com/questions/3110487/
				// echo '# '.$newdata['userid'].' ||| '.$newdata['tags']; return;

				$favtag = $newdata['tagname'];
				$mode = $newdata['mode'];

				if(isset($favtag) && isset($mode) && $mode=='add')
				{
					// remove commas and periods, punctuations
					// $test = str_replace(array("?","!",",",";"), "", $test);
					$favtag = preg_replace("#[[:punct:]]#", "", $favtag);

					// get all tags of user
					$userFavoriteTags = qa_db_read_one_value(
											qa_db_query_sub('SELECT tagwords FROM `^favtags`
														  WHERE userid = #
														 ', $userid), true);
					$userFavTagsNew = $favtag;
					if(!empty($userFavoriteTags))
					{
						// existing tags (string to array)
						$tagsArr = explode(',', $userFavoriteTags);
						// add chosen tags to fav tags if more than 1 tag
						$tagsArr[] = $favtag;
						// remove possible duplicates
						$tagsArr = array_unique($tagsArr);
						// sort alphabetically
						sort($tagsArr);
						$userFavTagsNew = implode(',', $tagsArr);
					}

					// insert-update tags string into table favtags
					qa_db_query_sub('INSERT INTO `^favtags` (userid, tagwords)
										VALUES (#, #)
										ON DUPLICATE KEY UPDATE userid=#, tagwords=#
										', $userid, $userFavTagsNew, $userid, $userFavTagsNew);

					// ajax return array data to write back into table
					$arrayBack = array(
						'favtags' => $favtag
					);
				}
				else if(isset($favtag) && $mode=='remove')
				{
					// get all tags of user
					$userFavoriteTags = qa_db_read_one_value(
											qa_db_query_sub('SELECT tagwords FROM `^favtags`
														  WHERE userid = #
														 ', $userid), true);
					// remove chosen tag
					$tagsArr = explode(',', $userFavoriteTags);
					while(($i = array_search($favtag, $tagsArr)) !== false)
					{
						unset($tagsArr[$i]);
					}
					sort($tagsArr);
					$userFavTagsNew = implode(',', $tagsArr);

					// insert new tag string into table favtags again
					qa_db_query_sub('UPDATE `^favtags`
										SET tagwords = #
										WHERE userid = #
										', $userFavTagsNew, $userid);

					// ajax return array data to write back into table
					$arrayBack = array(
						'favtags' => 'Tag successfully removed: '.$favtag.' # Survivors: '.$userFavTagsNew
					);
				}
				else
				{
					// problem
					$arrayBack = array(
						'favtags' => '# This category does not exist yet: '.$favtag
					);
				}

				echo json_encode($arrayBack);
				return;
			}
			// end ADDFAVTAG


			// start
			require_once QA_INCLUDE_DIR.'qa-app-posts.php';
			//require_once QA_INCLUDE_DIR.'qa-app-emails.php';

			// start content
			$qa_content = qa_content_prepare();

			// set CSS class in body tag
			qa_set_template('favtagsusersettings-page');


			// page title
			$qa_content['title'] = qa_lang('q2apro_favtagsmails_lang/subcat_nl');
			$qa_content['custom'] = ''; // init

			$userFavoriteTags = qa_db_read_one_value(
									qa_db_query_sub('SELECT tagwords FROM `^favtags`
												  WHERE userid = #
												 ', $userid), true);

			$tagtable = '<table class="tagtable"> <thead> <tr>
							<th>'.qa_lang('q2apro_favtagsmails_lang/username').'</th>
							<th>'.qa_lang('q2apro_favtagsmails_lang/favtags').'</th>
						 </tr> </thead>';

			// if we have favorited tags sort them
			$gotData = isset($userFavoriteTags) && $userFavoriteTags!='';
			if($gotData)
			{
				$taggArray = explode(',', $userFavoriteTags);
				sort($taggArray);

				$taglist = '<ul class="favtagsuserlist">';
				foreach($taggArray as $tagg)
				{
					$taglist .= '
					<li>
						<a href="./tag/'.$tagg.'">'.$tagg.'</a>
						<span class="qa-unfavorite-button-q2apro tooltipW" name="'.$tagg.'" title="'.qa_lang('q2apro_favtagsmails_lang/subcat_nl_tooltip1').'"></span>
					</li>';
				}
				$taglist .= '</ul>';
			}

			// save to user array
			// debug
			// $handle = qa_post_userid_to_handle($user['userid']);

			$qa_content['custom'] .= '
				<p>
					'.qa_lang('q2apro_favtagsmails_lang/subcat_nl_intro1').'
				</p>
				<p>
					'.qa_lang('q2apro_favtagsmails_lang/subcat_nl_intro2').'
				</p>
			';
			if($gotData)
			{
				$qa_content['custom'] .= '<p style="font-weight:bold;margin:30px 0 20px 0;">'.qa_lang('q2apro_favtagsmails_lang/subcat_nl_line1').'</p>';
				$qa_content['custom'] .= $taglist;
			}
			else
			{
				$qa_content['custom'] .= '<ul class="favtagsuserlist"></ul>';
			}

			$qa_content['custom'] .= '<p style="font-weight:bold;margin-top:50px;">'.qa_lang('q2apro_favtagsmails_lang/subcat_nl_line2').'</p>';
			$qa_content['custom'] .= '<form id="addnewtag" style="display:block;">
										<input style="font-size:14px;width:220px;padding:5px 5px;" value="" name="addfavtag" class="addfavtag" placeholder="'.qa_lang('q2apro_favtagsmails_lang/subcat_nl_line3').'" type="text">
										<input class="addfavtag_btn btnblue" type="submit" value="'.qa_lang('q2apro_favtagsmails_lang/subcat_nl_add').'" />
										<span class="sendrOff"></span>
									  </form>';

			$qa_content['custom'] .= '
			<script type="text/javascript">
				$(document).ready(function()
				{
					$("ul.favtagsuserlist").on("click", ".qa-unfavorite-button-q2apro", function() {
						var tagname = $(this).attr("name");
						var clickedFav = $(this);
						var dataArray = {
							tagname: tagname,
							mode: "remove"
						};
						var senddata = JSON.stringify(dataArray);
						console.log("sending: "+senddata);
						// send ajax
						$.ajax({
							 type: "POST",
							 url: "'.qa_self_html().'",
							 data: { ajaxdata: senddata },
							 dataType:"json",
							 cache: false,
							 success: function(data) {
								//dev
								console.log("server returned:"+data+" #Tags: "+data["tags"]);
								clickedFav.parent().html("<li>removed</li>").fadeOut(1050);
								$(".tipsy:last").remove();
							 }
						});
					});

					$("form#addnewtag").submit( function(ev) {
						ev.preventDefault();
						doAjaxPost();
						// if enter key and input field selected
						/*
						if(e.which == 13 && (focused.hasClass("addfavtag")) {
							doAjaxPost();
						}
						*/
					});

					function doAjaxPost() {
						var tagname = $(".addfavtag").val();
						if(tagname.length==0) {
							return;
						}
						var dataArray = {
							tagname: tagname,
							mode: "add"
						};
						var senddata = JSON.stringify(dataArray);
						console.log("sending: "+senddata);
						// send ajax
						$.ajax({
							 type: "POST",
							 url: "'.qa_self_html().'",
							 data: { ajaxdata: senddata },
							 dataType:"json",
							 cache: false,
							 success: function(data) {
								//dev
								console.log("server returned:"+data+" #Tags: "+data["tags"]);
								var tagg = data["favtags"];

								// check for # as indicator for error message
								if(tagg.indexOf("#") === -1) {
									// write tag to tag list
									$("ul.favtagsuserlist").append(\'<li> <a href="./tag/\'+tagg+\'">\'+tagg+\'</a> <span class="addedsuccess">&check;</span> <span class="qa-unfavorite-button-q2apro tooltipW" name="\'+tagg+\'" title="'.qa_lang('q2apro_favtagsmails_lang/subcat_nl_tooltip1').'"></span></li> \');
									$(".addedsuccess").fadeOut(1050);

									// clear input field
									$(".addfavtag").val("");
								}
								else {
									alert(tagg);
								}

							 }
						});
					}

				}); // end ready
			</script>';

			$qa_content['custom'] .= '
			<style type="text/css">
				h1 {
					margin-bottom:30px;
				}
				ul.favtagsuserlist {
				}
				.favtagsuserlist li {
					margin-bottom:20px;
					font-size:15px;
				}
				.favtagsuserlist li form, .favtagsuserlist li input {
					display:inline;
				}
				.qa-unfavorite-button-q2apro {
					display:inline-block;
					height:16px;
					width:16px;
					background:url('.$this->urltoroot.'remove.png) no-repeat;
					border:0;
					opacity:0.5;
					vertical-align:middle;
				}
				.qa-unfavorite-button-q2apro:hover {
					opacity:1.0;
				}
				.qa-sidepanel {
					display:none;
				}
				.qa-main {
					width:100%;
					padding-bottom:50px;
				}
				.addedsuccess {
					font-size:150%;
					color:#009;
				}
			</style>';


			return $qa_content;
		} // process_request

	} // end class

/*
	Omit PHP closing tag to help avoid accidental output
*/
