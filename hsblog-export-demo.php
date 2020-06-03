<?php
/*
 * Plugin Name: HSBlog Export Demo
 * Plugin URI: https://highspeedblog.com
 * Author: wiloke
 * Author URI: https://wiloke.com
 * Description: This plugin helps to import highspeed blog demos
 * Version 1.0
 */

use HSBlogCore\Helpers\GetPostMeta;

function zipData($folder, $archiveName)
{
    // Get real path for our folder
    
    // Initialize archive object
    $zip = new ZipArchive();
    $zip->open($archiveName, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    // Create recursive directory iterator
    /** @var SplFileInfo[] $files */
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $name => $file) {
        // Skip directories (they would be added automatically)
        if (!$file->isDir()) {
            // Get real and relative path for current file
            $filePath     = $file->getRealPath();
            $relativePath = substr($filePath, strlen($folder) + 1);
            
            // Add current file to archive
            $zip->addFile($filePath, $relativePath);
        }
    }
    
    // Zip archive will be created only after closing object
    $zip->close();
}

function hsblogZipFilesAndDownload($folder, $archiveName = "import.zip")
{
    //echo $file_path;die;
    //    $zip = new ZipArchive();
    //    //create the file and throw the error if unsuccessful
    //    if ($zip->open($archiveName, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) !== true) {
    //        wp_send_json_error(['msg' => 'Hoi oi Eden Tuan, chung ta khong the tao file zip']);
    //    }
    
    //    while ($file = readdir($dir)) {
    //        if (is_dir($pathdir.'/pageBuilder')) {
    //            $zip->addFile($pathdir.'/pageBuilder', 'pageBuilder');
    //        }
    //    }
    //
    //    $dir = opendir($pathdir.'/themeoptions/');
    //
    //    while ($file = readdir($dir)) {
    //        if (is_dir($pathdir.'/themeoptions')) {
    //            $zip->addFile($pathdir.'/themeoptions', 'themeoptions');
    //        }
    //    }
    
    zipData($folder, $archiveName);
    //    $zip->close();
    
    header("Content-type: application/zip");
    header("Content-Disposition: attachment; filename=$archiveName");
    header("Content-length: ".filesize($archiveName));
    readfile($archiveName);
    unlink($archiveName);
    exit;
}

add_action('admin_enqueue_scripts', function () {
    if (!current_user_can('administrator') || !isset($_GET['page']) || $_GET['page'] !== 'hsblog-export-demo') {
        return false;
    }
    
    wp_enqueue_script('hsblog-export', plugin_dir_url(__FILE__).'script.js', ['jquery'], '1.0', true);
});

add_action('wp_ajax_download_pagebuilder_archive', function () {
    if (!current_user_can('administrator')) {
        wp_send_json_error(['msg' => 'Eden Tuan moi ra ngoai']);
    }
    
    $query = new WP_Query(
        [
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'meta_key'       => '_wp_page_template',
            'meta_value'     => 'templates/blog-builder.php'
        ]
    );
    
    global $wp_filesystem;
    require_once(ABSPATH.'/wp-admin/includes/file.php');
    WP_Filesystem();
    $upload_dir = wp_upload_dir();
    $dirbase    = $upload_dir['basedir'];
    
    if (!file_exists($dirbase.'/edentuan')) {
        wp_mkdir_p($dirbase.'/edentuan');
    }
    $dirbase = $dirbase . "/edentuan";
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $content = GetPostMeta::getPurgeDataSettings($query->post->ID);
            if (empty($content)) {
                continue;
            }
            
            if (!file_exists($dirbase.'/pageBuilder')) {
                wp_mkdir_p($dirbase.'/pageBuilder');
            }
            
            if (!file_exists($dirbase.'/themeoptions')) {
                wp_mkdir_p($dirbase.'/themeoptions');
            }
            
            $fileDir = $dirbase.'/pageBuilder/'.$query->post->post_name.'.txt';
            if (!$wp_filesystem->put_contents($fileDir, json_encode($content), FS_CHMOD_FILE)) {
                wp_send_json_error(['msg' => 'Hoi oi Eden Tuan, toi khong the viet file '.$query->post->post_name]);
            }
            
//            $postSlugOption = 'wiloke_themeoptions_'.$query->post->post_name;
//            $themeoptions   = get_option($postSlugOption);
//            $fileDir        = $dirbase.'/themeoptions/'.$query->post->post_name.'.txt';
//            if (!$wp_filesystem->put_contents($fileDir, json_encode($themeoptions), FS_CHMOD_FILE)) {
//                wp_send_json_error([
//                    'msg' => 'Hoi oi Eden Tuan, toi khong the viet theme options '
//                             .$query->post->post_name
//                ]);
//            }
        };

        $themeoptions = get_option('wiloke_themeoptions');
        if (!$wp_filesystem->put_contents(
            $dirbase . '/themeoptions/themeoptions.txt', json_encode($themeoptions), FS_CHMOD_FILE
        )
        ) {
            wp_send_json_error(['msg' => 'Hoi oi Eden Tuan, toi khong the viet theme options']);
        }
    }
    
    hsblogZipFilesAndDownload($dirbase, 'import.zip');
    wp_send_json_success(['msg' => 'Eden Tuan, chung ta da hoan thanh nhiem vu']);
});

add_action('wp_ajax_hsblog_export', 'hsblogHandleExportFile');

function hsblogHandleExportFile()
{
    if (!current_user_can('administrator') || !isset($_GET['pageID'])) {
        return false;
    }
    
    $pageID  = abs($_GET['pageID']);
    $post    = get_post($pageID);
    $content = GetPostMeta::getPurgeDataSettings($pageID);
    
    $now = gmdate("D, d M Y H:i:s");
    header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
    header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
    header("Last-Modified: {$now} GMT");
    
    // force download
    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");
    
    // disposition / encoding on response body
    header("Content-Disposition: attachment;filename=".$post->post_name."-".date('Y-m-D').'.txt');
    header("Content-Transfer-Encoding: binary");
    
    echo json_encode($content);
    die();
}

function hsblogExportDemoSettings()
{
    $query = new WP_Query(
        [
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'meta_key'       => '_wp_page_template',
            'meta_value'     => 'templates/blog-builder.php'
        ]
    );
    
    $url = add_query_arg(
        [
            'pageID' => $query->post->ID,
            'action' => 'download_pagebuilder_archive'
        ],
        admin_url('admin-ajax.php')
    );

    ?>
    <a href="<?php echo esc_url($url); ?>" class="button button-primary js-hsblog-download">Download All PageBuilder</a>
    <?php
//    if ($query->have_posts()) {
//        while ($query->have_posts()) {
//            $query->the_post();
//            $content = GetPostMeta::getPurgeDataSettings($query->post->ID);
//
//            $url = add_query_arg(
//                [
//                    'pageID' => $query->post->ID,
//                    'action' => 'hsblog_export'
//                ],
//                admin_url('admin-ajax.php')
//            );
//            ?>
<!--            <div style="border: 1px solid red; margin-bottom: 20px; padding: 20px;">-->
<!--                <h1>--><?php //echo $query->post->post_title; ?><!--</h1>-->
<!--                <p>-->
<!--                    <label>Page Builder Data</label> <br/>-->
<!--                    <textarea rows="10" style="width: 90%;">--><?php //echo json_encode($content); ?><!--</textarea>-->
<!--                </p>-->
<!--                <p>-->
<!--                    <label>Theme Options</label> <br/>-->
<!--                    <textarea rows="10" style="width: 90%;">--><?php //echo json_encode($content); ?><!--</textarea>-->
<!--                </p>-->
<!--                <a href="--><?php //echo esc_url($url); ?><!--" class="js-hsblog-download">Download File</a>-->
<!--            </div>-->
<!--            --><?php
//        }
//    }
//    wp_reset_postdata();
}

add_action('admin_menu', function () {
    add_menu_page(
        'Hsblog Export Demo',
        'Hsblog Export Demo',
        'administrator',
        'hsblog-export-demo',
        'hsblogExportDemoSettings'
    );
});
