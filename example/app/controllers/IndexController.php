<?php
class IndexController{
    function home(){
        ww_view("/test/template",[
            "tests"=>[
                ["name"=>"xx1","detail"=>"detail1"],
                ["name"=>"xx2","detail"=>"detail2"],
                ["name"=>"xx3","detail"=>"detail3"],
                ["name"=>"xx4","detail"=>"detail4"],
            ]
        ]);
    }
}