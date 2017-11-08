<?php

/*
	Plugin Name: Newsletter FavTags
	Plugin URI: http://www.q2apro.com/plugins/newsletter
*/

	class q2apro_favtagsusers_page
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
					'title' => 'Favorite Tags All Users', // title of page
					'request' => 'Favorite Tags All Users', // request name
					'nav' => 'M', // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
				),
			);
		}

		// for url query
		function match_request($request)
		{
			if ($request=='favtagsusers')
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
			// return if permission level is not sufficient
			if(qa_user_permit_error('q2apro_favtagsmails_permission'))
			{
				$qa_content = qa_content_prepare();
				$qa_content['error'] = qa_lang_html('q2apro_favtagsmails_lang/access_forbidden');
				return $qa_content;
			}

			// AJAX post: we received post data, so it should be the ajax call to update the tags of the post
			$transferString = qa_post_text('ajaxdata'); // holds userid, favtags
			if(isset($transferString))
			{
				$newdata = json_decode($transferString,true);
				$newdata = str_replace('&quot;', '"', $newdata); // see stackoverflow.com/questions/3110487/
				// echo '# '.$newdata['userid'].' ||| '.$newdata['tags']; return;

				$userid = $newdata['userid'];
				$postfavtags = $newdata['tags'];

				if(!isset($userid) || !isset($postfavtags))
				{
					echo 'tags='.qa_lang_html('q2apro_quickedit_lang/access_problem');
					return;
				}
				else
				{
					// process new tags
					require_once QA_INCLUDE_DIR.'qa-app-posts.php';
					$tagsIn = str_replace(' ', ',', $postfavtags); // convert spaces to comma
					$tags = qa_post_tags_to_tagstring($tagsIn); // correctly parse tags string

					// sort the tags
					$arr = explode(',', $tags);
					asort($arr);
					$tags = implode(',', $arr);

					// 1 - update tag string in table favtags
					qa_db_query_sub('UPDATE `^favtags`
										SET tagwords = #
										WHERE userid = #
										', $tags, $userid);
				} // end db update

				// ajax return array data to write back into table
				$arrayBack = array(
					'userid' => $userid,
					'favtags' => $tags
				);
				echo json_encode($arrayBack);
				return;
			} // end POST data


			/* start */
			require_once QA_INCLUDE_DIR.'qa-app-posts.php';
			//require_once QA_INCLUDE_DIR.'qa-app-emails.php';

			// start content
			$qa_content = qa_content_prepare();

			// set CSS class in body tag
			qa_set_template('favtagsallusers-page');

			// page title
			$qa_content['title'] = 'Users and their favorited Tags'; // qa_lang_html('q2apro_favtagsmails_lang/page_title');
			$qa_content['custom'] = '';

			// do pagination
			$pag_start = (int)qa_get('start'); // gets start value from URL
			$pagesize = 200; // items per page
			$count = qa_db_read_one_value( qa_db_query_sub('SELECT COUNT(*) FROM `^favtags`')); // items total
			$qa_content['page_links'] = qa_html_page_links(qa_request(), $pag_start, $pagesize, $count, true); // last parameter is prevnext

			// BIG arrray that holds all data
			$userdata = array();

			$queryFavoriteTags = qa_db_query_sub('SELECT userid, tagwords FROM `^favtags`
													WHERE tagwords <> ""
													ORDER BY `userid` DESC
													LIMIT #,#
											', $pag_start, $pagesize);

			while ( ($row = qa_db_read_one_assoc($queryFavoriteTags,true)) !== null )
			{
				$userdata[$row['userid']]['userid'] = $row['userid'];
				$userdata[$row['userid']]['tagwords'] = $row['tagwords'];
			}

			$tagtable = '<table class="tagtable"> <thead> <tr>
							<th>n</th>
							<th>'.qa_lang('q2apro_favtagsmails_lang/username').'</th>
							<th>'.qa_lang('q2apro_favtagsmails_lang/about').'</th>
							<th>'.qa_lang('q2apro_favtagsmails_lang/favtags').'</th>
						 </tr> </thead>';

			$membercount = 1+$pag_start;
			foreach($userdata as $user)
			{
				$handle = qa_post_userid_to_handle($user['userid']);
				// get data from about field
				$about = qa_db_read_one_value( qa_db_query_sub('SELECT content FROM `^userprofile`
																	WHERE `content` > ""
																	AND `title` = "specialized"
																	AND userid = #
																	;', $user['userid']), true); //LIMIT 0, 30
				// get data from website field
				$website = qa_db_read_one_value( qa_db_query_sub('SELECT content FROM `^userprofile`
																	WHERE `content` > ""
																	AND `title` = "website"
																	AND userid = #
																	;', $user['userid']), true); //LIMIT 0, 30
				$webString = '';
				if(isset($website))
				{
					// $url_shown = ltrim($website, 'http://'); // remove http from URL, not gettting https
					$url_shown = preg_replace("(https?://)", "", $website); // remove http and https from URL
					$url_shown = rtrim($url_shown,'/'); // remove trailing slash from URL
					$websiteURL = 'http://'.$url_shown; // website URL
					$webString = '<a class="uweblink" title="'.$url_shown.'" href="'.$websiteURL.'">Website</a>';
				}

				$tagtable .= '
					<tr data-original="'.$user['userid'].'">
						<td>'.$membercount.'</td>
						<td><a href="./user/'.$handle.'">'.$handle.'</a>'.$webString.'</td>
						<td>'.$about.'</td>
						<td><div class="user_favtags_td"><input class="user_favtags" value="'.$user['tagwords'].'" /></div></td>
					</tr>';
				// <td><div class="user_specialized_td"><input class="user_specialized" value="'.htmlspecialchars($about, ENT_QUOTES, "UTF-8").'" /></div></td>
				$membercount++;
			}
			$tagtable .= "</table>";

			$qa_content['custom'] .= $tagtable;

			$qa_content['custom'] .= '
			<style type="text/css">
				.qa-sidepanel {
					display:none;
				}
				.qa-main {
					width:100%;
				}
				.uweblink {
					font-size:10px !important;
					display:block;
					color:#666;
				}
				table {
					width:90%;
					background:#EEE;
					margin:30px 0 15px;
					text-align:left;
					border-collapse:collapse;
				}
				table th {
					padding:4px;
					background:#cfc;
					border:1px solid #CCC;
					text-align:center;
				}
				table tr:nth-child(even){
					background:#EEF;
				}
				table tr:nth-child(odd){
					background:#F5F5F5;
				}
				table tr:hover {
					background:#FFD;
				}
				table th:nth-child(1), table td:nth-child(1) {
					width:5%;
					text-align:center;
				}
				table th:nth-child(2), table td:nth-child(2) {
					width:20%;
				}
				table th:nth-child(3), table td:nth-child(3) {
					width:35%;
				}
				table th:nth-child(4), table td:nth-child(4) {
					width:40%;
				}
				td {
					border:1px solid #CCC;
					padding:5px 10px;
					line-height:15px;
				}
				/*table.tagtable td:nth-child(3) {
					width:150px;
				}*/
				table.tagtable td a {
					font-size:12px;
				}
			    input.user_specialized, input.user_favtags, .inputdefault {
					width:100%;
					border:1px solid transparent;
					padding:3px;
					background:transparent;
				}
				input.user_specialized:focus, input.user_favtags:focus, .inputactive {
					background:#FFF !important;
					box-shadow:0 0 2px #7AF
				}
				.user_specialized_td, .user_favtags_td {
					position:relative;
				}
				.sendr,.sendrOff {
					padding:3px 10px;
					background:#FC0;
					border:1px solid #FEE;
					border-radius:2px;
					position:absolute;
					right:-77px;
					top:-5px;
					color:#123;
					cursor:pointer;
				}
				.sendrOff {
					text-decoration:none !important;
				}
			</style>';

			// JQUERY
			$qa_content['custom'] .= '
				<script type="text/javascript">
				$(document).ready(function(){
					var recentTR;
					$(".user_specialized, .user_favtags").click( function() {
						// remove former css
						$(".user_specialized, .user_favtags").removeClass("inputactive");
						recentTR = $(this).parent().parent().parent();
						recentTR.find("input.user_specialized, input.user_favtags").addClass("inputactive");
						// alert(recentTR.find("input.user_favtags").val());

						// add Update-Button if not yet added
						if(recentTR.find(".user_favtags_td").has(".sendr").length == 0) {
							// remove all other update buttons
							$(".sendr").fadeOut(200, function(){$(this).remove() });
							recentTR.find(".user_favtags_td").append("<a class=\'sendr\'>Update</a>");
						}
					});
					$(document).keyup(function(e) {
						// get focussed element
						var focused = $(":focus");
						// if enter key and input field selected
						if(e.which == 13 && (focused.hasClass("user_specialized") || focused.hasClass("user_favtags"))) {
							doAjaxPost();
						}
						// escape has been pressed
						else if(e.which == 27) {
							// remove all Update buttons and unfocus input fields
							$(".sendr").remove();
							// remove focus from input field
							$(":focus").blur();
							// remove active css class
							$(".user_specialized, .user_favtags").removeClass("inputactive");
						}
					});
					$(document).on("click", ".sendr", function() {
						doAjaxPost();
					});

					function doAjaxPost() {
						// get post data from <tr> element
						var userid = recentTR.attr("data-original");
						// var posttitle = recentTR.find("input.user_specialized").val();
						var favtags = recentTR.find("input.user_favtags").val();
						// alert(userid + " | " + posttitle + " | " + favtags);
						// var senddata = "userid="+userid+"&title="+posttitle+"&tags="+favtags;
						var dataArray = {
							userid: userid,
							tags: favtags
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

								// prevent another click on button by assigning another class id
								$(".sendr").attr("class","sendrOff");
								// show success indicator checkmark
								recentTR.find(".sendrOff").css("background", "#55CC55");
								recentTR.find(".sendrOff").html("<span style=\'font-size:150%;\'>&check;</span>");

								// write title back to posttitle input field
								recentTR.find("input.user_specialized").val(data["title"]);
								// write tags back to tags input field
								recentTR.find("input.user_favtags")val(data["tags"]);

								// remove update button
								recentTR.find(".sendrOff").fadeOut(1500, function(){$(this).remove() });
								// remove focus from input field
								$(":focus").blur();
								// remove active css class
								$(".user_specialized, .user_favtags").removeClass("inputactive");
							 }
						});
					}
				});

				</script>';

				return $qa_content;
		} // process_request

	} // end class

/*
	Omit PHP closing tag to help avoid accidental output
*/
