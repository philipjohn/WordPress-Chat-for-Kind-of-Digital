<?php
global $chat;

echo $chat->chat_box($a);
echo $chat->chat_wrap($a);
echo $chat->chat_area($a);

if ( $chat_localized['type_'.$a['id']] ) {
    
    echo $chat->chat_message_area($a);
} else {
    if ($chat->use_twitter_auth($a) or $chat->use_facebook_auth($a) or $chat->use_public_auth($a)) {
	if ($chat->use_public_auth($a)) {
	    echo '<div class="login-message">'.__('To get started just enter your email address and desired username', $chat->translation_domain).': </div>';
	    echo $chat->chat_login_public($a, false);
	}
	if ($chat->use_twitter_auth($a) or $chat->use_facebook_auth($a)) { 
            echo '<div class="login-message">Log in using your: </div>';
            echo '<div class="chat-login-wrap">';
	    
	    echo $chat->chat_login_twitter($a, false);
	    echo $chat->chat_login_facebook($a, false);
	    
	    echo '</div>';
	}
    } else {
	echo '<div class="login-message"><strong>' . __('You must be logged in to participate in chats', $chat->translation_domain) . '</strong></div>';
    }
    
    echo $chat->chat_dummy_message_area($a, false);
}

if ( $a['log_display'] == 'enabled' &&  $a['id'] != 1) {
    $dates = $chat->get_archives($a['id']);

    if ( $dates && is_array($dates) ) {
	echo '<br />';
	echo '<div class="chat-note"><p><strong>' . __('Chat Logs', $chat->translation_domain) . '</strong></p></div>';
	foreach ($dates as $date) {
	    $date_content .= '<li><a class="chat-log-link" style="text-decoration: none;" href="' . $a['permalink'] . $a['url_separator'] . 'lid=' . $date->id . '">' . date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime($date->start) + get_option('gmt_offset') * 3600, false) . ' - ' . date_i18n(get_option('date_format').' '.get_option('time_format'), strtotime($date->end) + get_option('gmt_offset') * 3600, false) . '</a>';
	    if (isset($_GET['lid']) && $_GET['lid'] == $date->id) {
		$_POST['cid'] = $a['id'];
		$_POST['archived'] = 'yes';
		$_POST['function'] = 'update';
		$_POST['since'] = strtotime($date->start);
		$_POST['end'] = strtotime($date->end);
		$_POST['date_color'] = $a['date_color'];
		$_POST['name_color'] = $a['name_color'];
		$_POST['moderator_name_color'] = $a['moderator_name_color'];
		$_POST['text_color'] = $a['text_color'];
		$_POST['date_show'] = $a['date_show'];
		$_POST['time_show'] = $a['time_show'];
		$_POST['avatar'] = $a['avatar'];
		
		$date_content .= '<div class="chat-wrap avatar-'.$a['avatar'].'" style="background-color: '.$a['background_color'].'; '.$a['font_style'].'"><div class="chat-area" >';
		$date_content .= $chat->process('yes');
		$date_content .= '</div></div>';
	    }
	    $date_content .= '</li>';
	}
	
	echo '<div id="chat-log-wrap-'.$a['id'].'" class="chat-log-wrap" style="background-color: '.$a['background_color'].'; '.$a['font_style'].'"><div id="chat-log-area-'.$a['id'].'" class="chat-log-area"><ul>' . $date_content . '</ul></div></div>';
    }
}

echo '<div class="chat-clear"></div></div>';
echo '<style type="text/css">#chat-box-'.$a['id'].'.new_msg { background-color: '.$a['background_highlighted_color'].' !important; }</style>';