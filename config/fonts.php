<?php

/*
| Fonts for Dari and Pashto text. One is chosen for Dari and one for Pashto in System Settings.
| Only the chosen font is sent to the browser, once, and then kept by it.
| English text and numbers always keep the usual font of the system.
|
| faces: [file, format, font-weight]. A variable font has one file for every weight ("400 700").
| 'pashto' => false: the font has no Pashto letters (ټ ډ ړ ښ ږ ګ ڼ ې ۍ); they are shown in the device's own font.
| Licences: public/fonts/LICENSES.txt
*/

return [
    'vazirmatn' => ['name' => 'Vazirmatn', 'pashto' => true, 'faces' => [['vazirmatn.woff2', 'woff2', '100 900']]],
    'noto-naskh-arabic' => ['name' => 'Noto Naskh Arabic', 'pashto' => true, 'faces' => [['noto-naskh-arabic.woff', 'woff', '400 700']]],
    'noto-kufi-arabic' => ['name' => 'Noto Kufi Arabic', 'pashto' => true, 'faces' => [['noto-kufi-arabic.woff', 'woff', '400 700']]],
];
