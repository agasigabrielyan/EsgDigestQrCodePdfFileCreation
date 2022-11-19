<?php
define('PUBLIC_AJAX_MODE', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// обязательно проверяем сессию
check_bitrix_sessid() || die();

use lib\EsgDigest;

if ( isset($_REQUEST['action']) && $_REQUEST['action'] === 'create-digets-pdf-file' ) {
    $pdfFile = (new EsgDigest())->createPdfFilesOfDigest( $_REQUEST['elementsIds'] );
    if( $pdfFile ) {
        echo json_encode('success' );
    }
}


