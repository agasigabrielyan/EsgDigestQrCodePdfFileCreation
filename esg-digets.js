let eventsToBeUsed = [
    'Grid::allRowsSelected',
    'Grid::selectRow',
];
for (let i=0; i<eventsToBeUsed.length; i++) {
    BX.addCustomEvent(eventsToBeUsed[i], BX.delegate(function(data){
        createPublishButton();
    }));
}

/** 1) метод добавляет кнопку Опубликовать при выделении элементов на странице */
function createPublishButton() {
    let isPublishButtonExists = document.getElementById('grid_elements_publish_button');
    // если кнопка еще не существует добавляем ее
    if(!isPublishButtonExists) {
        let mainGridControlPanelCell = document.querySelector(".main-grid-control-panel-cell");
        let publishButton = BX.create(
            "span",
            {
                props: {
                    className: 'main-grid-panel-control-container',
                    id: 'grid_elements_publish_button'
                },
                children: [
                    BX.create(
                        "span",
                        {
                            props: {
                                className: "main-grid-buttons"
                            },
                            text: "Опубликовать отмеченные"
                        },
                    ),
                ],
                events: {
                    click: function() {
                        activateArticleAndCreateQrCode()
                    }
                }
            }
        );
        BX.append(publishButton, mainGridControlPanelCell);
    }
}

/** 2) метод отправляет ajax запрос для создания pdf файла с дайджестом новостей и создания элементов в инфоблоке */
function activateArticleAndCreateQrCode() {
    BX.showWait();
    let mainGridRowChecked = document.getElementsByClassName("main-grid-row-checked");
    let elementsIds = [];

    for(let i=0; i<mainGridRowChecked.length; i++) {
        if( mainGridRowChecked[i].dataset.id && mainGridRowChecked[i].dataset.id !== "template_0" ) {
            elementsIds.push( mainGridRowChecked[i].dataset.id );
        }
    }

    let data = {
        action: "create-digets-pdf-file",
        sessid: BX.bitrix_sessid(),
        elementsIds: elementsIds
    };

    BX.ajax({
        url: "/local/php_interface/lib/EsgDigest/esgDagestAjax.php",
        data: data,
        method: 'POST',
        dataType: 'json',
        onsuccess: function( result ) {
            window.location.href = "/cpgp/esg-daydzhest/";
        },
        onfailure: function( error ){
            debugger;
            window.location.reload();
        },
    });
}