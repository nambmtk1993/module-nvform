<?php

/**
 * @Project NUKEVIET 4.x
 * @Author VINADES.,JSC (contact@vinades.vn)
 * @Copyright (C) 2014 VINADES.,JSC. All rights reserved
 * @License GNU/GPL version 2 or any later version
 * @Createdate 24-06-2011 10:35
 */

if( ! defined( 'NV_IS_FILE_ADMIN' ) ) die( 'Stop!!!' );

$fid = $nv_Request->get_int( 'fid', 'get', 0 );
$question_data = $answer_data = array();

// Xoa cau tra loi
if( $nv_Request->isset_request( 'del', 'post' ) )
{
	if( ! defined( 'NV_IS_AJAX' ) ) die( 'Wrong URL' );

	$aid = $nv_Request->get_int( 'aid', 'post', 0 );

	if( empty( $aid ) ) die( 'NO' );

	$answer = $db->query( 'SELECT answer FROM ' . NV_PREFIXLANG . '_' . $module_data . '_answer WHERE id = ' . $aid )->fetchColumn();

	$sql = 'DELETE FROM ' . NV_PREFIXLANG . '_' . $module_data . '_answer WHERE id = ' . $aid;
	if( $db->exec( $sql ) )
	{
		if( !empty( $answer ) )
		{
			$answer = unserialize( $answer );
			foreach( $answer as $qid => $ans )
			{
				$question_type = $db->query( 'SELECT question_type FROM ' . NV_PREFIXLANG . '_' . $module_data . '_question WHERE qid = ' . $qid )->fetchColumn();
				if( $question_type == 'file' AND file_exists( NV_UPLOADS_REAL_DIR . '/' . $module_upload . '/' . $ans ) )
				{
					@nv_deletefile( NV_UPLOADS_REAL_DIR . '/' . $module_upload . '/' . $ans );
				}
			}
		}
	}
	nv_del_moduleCache( $module_name );
	die('OK');
}

$xtpl = new XTemplate( 'report.tpl', NV_ROOTDIR . '/themes/' . $global_config['module_theme'] . '/modules/' . $module_file );
$xtpl->assign( 'LANG', $lang_module );
$xtpl->assign( 'GLANG', $lang_global );
$xtpl->assign( 'NV_BASE_SITEURL', NV_BASE_SITEURL );

$sql = 'SELECT t1.*, t2.username FROM ' . NV_PREFIXLANG . '_' . $module_data . '_answer t1 LEFT JOIN ' . NV_USERS_GLOBALTABLE . ' t2 ON t1.who_answer = t2.userid WHERE fid = ' . $fid;
$result = $db->query( $sql );
$answer_data = $result->fetchAll();

$sql = 'SELECT * FROM ' . NV_PREFIXLANG . '_' . $module_data . '_question WHERE fid = ' . $fid;
$result = $db->query( $sql );

while( $row = $result->fetch() )
{
	$question_data[$row['qid']] = $row;
	$row['title_cut'] = nv_clean60( $row['title'], 40 );
	$xtpl->assign( 'QUESTION', $row );
	$xtpl->parse( 'main.thead' );
}

$i = 1;
foreach( $answer_data as $answer )
{
	$answer['answer'] = unserialize( $answer['answer'] );

	foreach( $answer['answer'] as $qid => $ans )
	{
		if( isset( $question_data[$qid] ) )
		{
			$question_type = $question_data[$qid]['question_type'];
			if( $question_type == 'multiselect' OR $question_type == 'select' OR $question_type == 'radio' OR $question_type == 'checkbox' )
			{
				$data = unserialize( $question_data[$qid]['question_choices'] );
				if( $question_type == 'checkbox' )
				{
					$result = explode( ',', $ans );
					$ans = '';
					foreach( $result as $key )
					{
						$ans .= $data[$key] . "<br />";
					}
				}
				else
				{
					$ans = $data[$ans];
				}
			}
			elseif( $question_type == 'date' and !empty( $ans ) )
			{
				$ans = nv_date( 'd/m/Y', $ans );
			}
			elseif( $question_type == 'time' and !empty( $ans ) )
			{
				$ans = nv_date( 'H:i', $ans );
			}
			elseif( $question_type == 'grid' )
			{
				$data = unserialize( $question_data[$qid]['question_choices'] );
				$result = explode( '||', $ans );
				foreach( $data['col'] as $col )
				{
					if( $result[0] == $col['key'] )
					{
						$ans = $col['value'];
						break;
					}
				}
				foreach( $data['row'] as $row )
				{
					if( $result[1] == $row['key'] )
					{
						$ans .= ' - ' . $col['value'];
						break;
					}
				}
			}
			elseif( $question_type == 'file' and file_exists( NV_UPLOADS_REAL_DIR . '/' . $module_upload . '/' . $ans ) )
			{
				$ans = '<a href="' . NV_BASE_SITEURL . NV_UPLOADS_DIR . '/' . $module_upload . '/' . $ans . '" title="">' . $lang_module['question_options_file_dowload'] . '</a>';
			}
			else
			{
				$ans = '';
			}
		}
		else
		{
			$ans = '';
		}

		$answer['username'] = empty( $answer['username'] ) ? $lang_module['report_guest'] : $answer['username'];

		$xtpl->assign( 'ANSWER', $ans );

		$xtpl->parse( 'main.tr.td' );
	}

	$answer['answer_time'] = nv_date( 'd/m/Y H:i', $answer['answer_time'] );
	$answer['answer_edit_time'] = ! $answer['answer_edit_time'] ? '<span class="label label-danger">N/A</span>' : nv_date( 'd/m/Y H:i', $answer['answer_edit_time'] );

	$answer['no'] = $i;
	$xtpl->assign( 'ANSWER', $answer );
	$xtpl->parse( 'main.tr' );
	$i++;
}

$sql = 'SELECT title FROM ' . NV_PREFIXLANG . '_' . $module_data . ' WHERE id = ' . $fid;
$result = $db->query( $sql );
list( $title ) = $result->fetch( 3 );
$page_title = sprintf( $lang_module['report_page_title'], $title );

unset( $answer_data, $question_data );

$xtpl->assign( 'FID', $fid );

$xtpl->parse( 'main' );
$contents = $xtpl->text( 'main' );

include NV_ROOTDIR . '/includes/header.php';
echo nv_admin_theme( $contents );
include NV_ROOTDIR . '/includes/footer.php';