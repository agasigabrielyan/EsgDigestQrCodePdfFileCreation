<?php
/**
* Класс создает QR код для вновь созданного элемента списка Esg-digest
* При публикации отмеченных элементов списка класс формирует PDF файл и записываем в инфоблок 140
*
*/

namespace lib;
use Matrix\Exception;
use QRcode;

class EsgDigest {
    const EDigestsUniversalListIblockId = 126;
        const EDigestUniversalListApiCode = "esgdigest";
    const EDigetsPdfFilesIblockId = 140;
        const EDigetsPdfFilesApiCode = "esgdaydzhest";

    /**
     * Метод создает pdf файл на основании отмеченных пользователем элементов
     * на странице списка /services/lists/126/view/0/
     *
     * @return null
     */
    public function createPdfFilesOfDigest( $elements ) {
        $createdDigestId = null;

        // 1) получим все данные по указанным элементам универсального списка
        $dbElements = \CIBlockElement::GetList(
            ["SORT"=>'ASC'],
            ['IBLOCI_ID' => self::EDigestsUniversalListIblockId, 'ID' => $elements],
            false,
            false,
            ['*']
        );

        $elements = array();
        while($objRow = $dbElements -> GetNextElement()) {
            $row = array_merge(
                $objRow -> getFields(),
                array('PROPERTIES' => $objRow -> getProperties()));
            $elements[] = $row;
        }

        foreach ( $elements as $elementKey => $elementValue ) {
            $mainImage = \CFile::GetPath($elementValue['PROPERTIES']['IZOBRAZHENIE']['VALUE']);
            $qrCodeImagePath = \CFile::GetPath($elementValue['PROPERTIES']['QR_KOD_SSYLKI']['VALUE']);

            $elements[$elementKey]['QR_CODE_IMAGE_PATH'] = $qrCodeImagePath;
            $elements[$elementKey]['MAIN_PAGE'] = $mainImage;
        }

        // 2) Создадим файл дайджеста и получим путь к нему
        $digetsFilePath = $this->generateDigestFile( $elements );

        // 3) создать элемент инфоблока дайджеста
        $newEl = new \CIBlockElement();
        $PROPS = [];
        $PROPS['ESG_DIGEST'] = \CFile::MakeFileArray( __DIR__ . $digetsFilePath );
        $arFields = [
            'NAME' => date('Y-m-d H:i:s'),
            "IBLOCK_SECTION_ID" => false,
            "IBLOCK_ID"      => self::EDigetsPdfFilesIblockId,
            'PROPERTY_VALUES' => $PROPS
        ];

        $DIGEST_ID = $newEl->Add( $arFields );

        $files = glob( __DIR__ . '/mpdf_tmp_generated_files/*');
        foreach($files as $file){ // iterate files
            if(is_file($file)) {
                unlink($file); // delete file
            }
        }



        // 4) удалить все элементы универсального списка, на основании которых создан файл pdf-дайджеста
         foreach ( $elements as $elValue ) {
             \CIBlockElement::Delete( $elValue['ID'] );
        }

        if( $DIGEST_ID > 0 ) {
            $createdDigestId = $DIGEST_ID;
        }
        return $createdDigestId;
    }

    /**
     * Метод создает файл дайдежста и возвращает путь к созданному файлу
     *
     * @param array $elements
     * @return string
     * @throws \Mpdf\MpdfException
     */
    private function generateDigestFile(array $elements ) {
        $digetsFilePath = "";

        global $DB;
        $currentDateInSiteDateFormat = date($DB->DateFormatToPHP(\CSite::GetDateFormat("SHORT")), time());
        $digestTitle = "Дайджест новостей: " . $currentDateInSiteDateFormat;

        $digestHtml = "<div>";
        $digestHtml .= "<h1>" . $digestTitle . "</h1>";
        $digestHtml .= "<br/><br/>";
        $digestHtml .= "<table style='border: 1px solid #ccc;'>";
        foreach ( $elements as $elValue ) {
            $digestHtml .= "<tr>";

            $digestHtml .= "<td style='width: 150px;'>";
            //$digestHtml .= "(<b style='font-size: 8px;'>" . $elValue['PROPERTIES']['RAZDEL_ESG_DAYDZHESTA']['VALUE'] . "</b>)<br/>";
            $digestHtml .= $elValue['NAME'];
            $digestHtml .= "</td>";

            $digestHtml .= "<td>";
            $digestHtml .= "<img style='width: 150px' src='" . $_SERVER['DOCUMENT_ROOT'] . $elValue['MAIN_PAGE'] . "' />";
            $digestHtml .= "</td>";

            $digestHtml .= "<td style='width: 300px; white-space: normal; overflow: hidden;'>";
            $digestHtml .= $elValue['PREVIEW_TEXT'];
            $digestHtml .= "</td>";

            $digestHtml .= "<td>";
            foreach ($elValue['PROPERTIES']['KHESHTEGI']['VALUE'] as $kheshtegKey => $kheshtegValue) {
                $digestHtml .= $kheshtegValue;
                if( ($kheshtegKey+1) != count($elValue['PROPERTIES']['KHESHTEGI']['VALUE']) ) {
                    $digestHtml .= "<br/> ";
                }
            }
            $digestHtml .= "</td>";

            $digestHtml .= "<td>";
            $digestHtml .= "<img style='width: 150px' src='" . $_SERVER['DOCUMENT_ROOT'] . $elValue['QR_CODE_IMAGE_PATH'] . "' />";
            $digestHtml .= "</td>";

            $digestHtml .= "</tr>";
        }
        $digestHtml .= "</table>";
        $digestHtml .= "</div>";

        require_once __DIR__ . '/mpdf/vendor/autoload.php';

        $mpdf = new \Mpdf\Mpdf(['orientation' => 'L']);

        $mpdf->WriteHTML( $digestHtml );
        $digestName = "digest_" . date($DB->DateFormatToPHP(\CSite::GetDateFormat("SHORT")), time()) . ".pdf";
        $mpdf->Output(__DIR__ . '/mpdf_tmp_generated_files/' . $digestName);

        $digetsFilePath = '/mpdf_tmp_generated_files/' . $digestName;

        return $digetsFilePath;
    }

    /**
     * Метод создает файл QRкода, сохраняет его во временной папке phpqrcode_tmp_generated_files
     * Возвращает путь к временному файлу
     *
     * @param $el
     * @return string
     */
    public function generateQrCodeTemporaryFile( array $el ) {
        require_once __DIR__ . '/phpqrcode/qrlib.php';
        $qrcodeImagePath = null;

        $fileName = $el['ID'] . "_" . \CUtil::translit( $el['NAME'],'ru' );

        QRcode::png($el['SSYLKA_NA_ISTOCHNIK_VALUE'], __DIR__ . '/phpqrcode_tmp_generated_files/' . $fileName . '.png');

        $qrcodeImagePath =  __DIR__ . '/phpqrcode_tmp_generated_files/' . $fileName . '.png';

        return $qrcodeImagePath;
    }

    /**
     * Метод создаем изображение - QRкод и записываем в свойство созданного элемента универсального списка (IBLOCK_ID  - 126)
     * Метод вызывается из init.php на событии OnAfterIblockElementAdd
     *
     * @param $elementsIds
     * @return array
     */
    public function activateElements( array $elementsIds ) {
        $elementsToBeUpdated = [];

        $dbResult = \Bitrix\Iblock\Elements\ElementEsgdigestTable::getList([
            'select' => [
                'ID',
                'NAME',
                'SSYLKA_NA_ISTOCHNIK_' => 'SSYLKA_NA_ISTOCHNIK'
            ],
            'filter' => [
                'ID' => $elementsIds
            ]
        ]);

        $elementsToBeUpdated = [];
        while ( $el = $dbResult -> Fetch() ) {
            $qrcodeImagePath = $this->generateQrCodeTemporaryFile( $el );

            $elementsToBeUpdated[$el['ID']]['DATA'] = $el;
            $elementsToBeUpdated[$el['ID']]['QR_IMAGE_PATH'] = $qrcodeImagePath;
        }

        // обновим элементы в свойство типа файл вложим сформированный QR код и установим активность в значение Y
        foreach ( $elementsToBeUpdated as $elementId => $value ) {
            // QR_KOD_SSYLKI

            $el = new \CIBlockElement();
            $PROPS = array();
			$PROPS['QR_KOD_SSYLKI'] = \CFile::MakeFileArray($value['QR_IMAGE_PATH']);

            \CIBlockElement::SetPropertyValuesEx ($elementId, self::EDigestsUniversalListIblockId, $PROPS);
        }

        $files = glob( __DIR__ . '/phpqrcode_tmp_generated_files/*');
        foreach($files as $file){ // iterate files
            if(is_file($file)) {
                unlink($file); // delete file
            }
        }

        return $elementsToBeUpdated;
    }
}