<?php
/*
//dataform routing
Burp::post(null, 'process=1', ['as' => 'save', static function () {
    BurpEvent::queue('dataform.save');
}]);

//datagrid routing
Burp::get(null, 'page/(\d+)', ['as' => 'page', static function ($page) {
    BurpEvent::queue('dataset.page', [$page]);
}]);

Burp::get(null, 'ord=(-?)(\w+)', ['as' => 'orderby', static function ($direction, $field) {
    $direction = ('-' == $direction) ? 'DESC' : 'ASC';
    BurpEvent::queue('dataset.sort', [$direction, $field]);
}])->remove('page');

//todo: dataedit
*/
Route::group(['middleware' => 'web'], static function () {
    Route::get('rapyd-ajax/{hash}', ['as' => 'rapyd.remote', 'uses' => '\Zofe\Rapyd\Controllers\AjaxController@getRemote']);

    Route::namespace('Zofe\Rapyd\Demo')->prefix('rapyd-demo')->group(static function () {
        Route::get('/', 'DemoController@getIndex');
        Route::get('/models', 'DemoController@getModels');
        Route::get('/schema', 'DemoController@getSchema');
        Route::get('/menus-schema', 'DemoController@getMenusSchema');
        Route::get('/set', 'DemoController@getSet');
        Route::get('/grid', 'DemoController@getGrid');
        Route::get('/filter', 'DemoController@getFilter');
        Route::get('/customfilter', 'DemoController@getCustomfilter');
        Route::any('/form', 'DemoController@anyForm');
        Route::any('/advancedform', 'DemoController@anyAdvancedform');
        Route::any('/styledform', 'DemoController@anyStyledform');
        Route::any('/edit', 'DemoController@anyEdit');
        Route::any('/datatree', 'DemoController@anyDatatree');
        Route::any('/menuedit', 'DemoController@anyMenuedit');
        Route::get('/nudegrid', 'DemoController@getNudegrid');
        Route::any('/nudeedit', 'DemoController@anyNudeedit');
        Route::any('/embed', 'DemoController@getEmbed');
        Route::get('/author-list', 'DemoController@getAuthorlist');
        Route::get('/category-list', 'DemoController@getCategorylist');
    });
});
