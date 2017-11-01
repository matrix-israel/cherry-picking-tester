<?php
/**
 *
 * Copyright (C) 2017 Amiel Elboim - Matrix Israel <amielel@matrix.co.il>
 *
 * @package Clinikal
 * @author Amiel Elboim <amielel@matrix.co.il>
 * @link https://github.com/matrix-israel
 *
 */


if(empty($_GET['commit'])){
    $output = "<!DOCTYPE html>
                <html lang=\"en\">
                <head>
                    <meta charset=\"UTF-8\">
                    <title>Cherry picker tester</title>
                </head>
                <body>
                    <form method='get'>
                        <h3>Enter commit SHA</h3>
                        <input type='text' name='commit'>
                        <input type='submit' value='Test'>
                    </form>
                </body>
                </html>";
    echo $output;
    die;
}


$tree = array();

$commit_picked = $_GET['commit'];
$allDepsCommits = array();
$allDepsFiles = array();

//github url
exec("git config --get remote.origin.url",$origin_location);
$origin_location = str_replace(array('git@','http@','.git'), array('','',''),$origin_location[0]);
$origin_location = 'https://' . str_replace(':',"/", $origin_location);

//message of required commit
exec("git log --format=%B -n 1  $commit_picked", $commit_message_arr);
$commit_message = '';
foreach ($commit_message_arr as $line) $commit_message .= $line . ' ';
//message of HEAD
exec("git log --format=%B -n 1  HEAD", $head_message_arr);
$head_message = '';
foreach ($head_message_arr as $line) $head_message .= $line . ' ';


/**
 * create binary tree with all dependencies commits.
 * this function is called recursive.
 * @param $commit
 * @param $tree
 */
function getAllDepsCommits($commit, &$tree){
    global $allDepsCommits;
    global $allDepsFiles;

    //get cahges files in the commit
    exec("git diff-tree --no-commit-id --stat -r $commit",$changed_files);

    array_pop($changed_files);

    if(!isset($allDepsFiles[$commit])){
        $allDepsFiles[$commit] = array();
    }

    foreach ($changed_files as $file) {

        $full_file = $file;
        $file = trim(explode('|',$file)[0]);

        if (!in_array($file, $allDepsFiles[$commit])) {
            $allDepsFiles[$commit][$file] = $file;
        } else {
                continue;
        }

        //get deps commits
        $depsCommit = array();
        exec("git log --date-order --date=short --pretty=format:\"%h%x09%an%x09%ad%x09%s\" --reverse HEAD..$commit -- $file", $depsCommit);

        foreach ($depsCommit as $k => $singleCommit) {

            $commitHash = substr($singleCommit,0,7);

            if($commitHash == substr($commit,0,7)){
                unset($depsCommit[$k]);
                continue;
            }

            if (!in_array($commitHash, $allDepsCommits)) {
               // exec("", $depsCommit)
                $allDepsCommits[$commitHash] = $singleCommit;
            }

            if( $commitHash != substr($commit,0,7)){
                $depsCommit[$singleCommit] = array();
                getAllDepsCommits($commitHash,$depsCommit[$singleCommit]);
            }
            unset($depsCommit[$k]);

        }

        $tree[$full_file] = $depsCommit;
    }

}

//make html list for 'OrgChart' library
function makeList($array) {

    //Base case: an empty array produces no list
    if (empty($array)) return '';

    //Recursive Step: make a list with child lists
    $output = '<ul id="ul-data">';
    foreach ($array as $key => $subArray) {
        if(is_array($subArray)){
            $output .= '<li>' . $key  . makeList($subArray) . '</li>';
        } else {
            $output .= '<li>' . $subArray  . '</li>';
        }

    }
    $output .= '</ul>';

    return $output;
}


getAllDepsCommits($commit_picked,$tree[substr($_GET['commit'],0,7)]);
//echo "<pre>";
//print_r($tree);die;
$ul = makeList($tree);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cherry picker tester</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://rawgit.com/dabeng/OrgChart/master/dist/css/jquery.orgchart.min.css">
    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <style>
        .orgchart .node{
            width: 250px;
        }

        .orgchart .node .title{
            height: 50px;
        }
        .show-diff, .find-prev{
            position: absolute;
            left:5px;
            bottom: 3px;
            cursor: pointer;
            display: inline-block;
            z-index: 100;
            font-size: 10px;
            margin: 7px;

        }
        .find-prev{
            left: 170px;

        }
        h3{
            text-align: center;
        }
        .link{
            cursor: pointer;
            color: blue;
        }

        #go-to-main, #close-tree{
            position: fixed;
            right: 10px;
        }
        #go-to-main{
            top: 120px;
        }
        #close-tree{
            top:160px;
        }

        /* Style the tab */
        div.tab {
            overflow: hidden;
            border: 1px solid #ccc;
            background-color: #f1f1f1;
        }

        /* Style the buttons inside the tab */
        div.tab button {
            background-color: inherit;
            float: left;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 14px 16px;
            transition: 0.3s;
        }

        /* Change background color of buttons on hover */
        div.tab button:hover {
            background-color: #ddd;
        }

        /* Create an active/current tablink class */
        div.tab button.active {
            background-color: #ccc;
        }

        /* Style the tab content */
        .tabcontent {
            display: none;
            padding: 6px 12px;
            border: 1px solid #ccc;
            border-top: none;
        }

        .tabcontent {
            -webkit-animation: fadeEffect 1s;
            animation: fadeEffect 1s; /* Fading effect takes 1 second */
        }

        @-webkit-keyframes fadeEffect {
            from {opacity: 0;}
            to {opacity: 1;}
        }

        @keyframes fadeEffect {
            from {opacity: 0;}
            to {opacity: 1;}
        }

    </style>
</head>
<body>
<div style="display: none">
    <?php echo $ul; ?>
</div>
<h3>Cherry pick of commits <span class="link"  title="<?php echo $commit_message ?>"><a target="_blank" href="<?php echo $origin_location . '/' . $commit_picked?>"><?php echo $commit_picked ?></a></span></h3>

<div class="tab">
    <button class="tablinks" id="defaultOpen" onclick="openTab(event, 'gauge-charts')">General evaluation</button>
    <button class="tablinks" onclick="openTab(event, 'chart-container')">Learn about dependencies</button>
    <button class="tablinks" onclick="openTab(event, 'chartMainContainer')">Statics about required changes</button>
    <button class="tablinks" onclick="openTab(event, 'chartContainer')">Statics about all files</button>
</div>
<!-- div for general gauge charts -->
<div id="gauge-charts" class="tabcontent">

</div>
<!-- div for tree of deps -->
<div id="chart-container" class="tabcontent">
    <h3>All the dependencies commits between <span class="link" title="<?php echo $head_message ?>">HEAD</span> to <span class="link" title="<?php echo $commit_message ?>"><?php echo $commit_picked ?></span></h3>

</div>
<!-- div for statistic of file from required commit -->
<div id="chartMainContainer" class="tabcontent" style="width:90%; height: 600px;"></div>
<!-- div for statistic of all deps files -->
<div id="chartContainer"  class="tabcontent" style="width:90%; height: 600px;"></div>


</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script src="https://rawgit.com/dabeng/OrgChart/master/dist/js/jquery.orgchart.min.js"></script>
<script src="https://canvasjs.com/assets/script/canvasjs.min.js"></script>
<script type="text/javascript" src="https://cdn.plot.ly/plotly-1.5.0.min.js"></script>
<script>

    $(function() {

        $('.link').tooltip();

        var c =$('#chart-container').orgchart({
            'data' : $('#ul-data'),
            'collapsed': true,
            'initCompleted':function () {
                $('.title').append('<button class="show-diff">Show details from Github</button>');


                $('.show-diff').on('click', function () {

                    var boxText = $(this).parent().text();
                    boxText = boxText.replace('Show details from Github','');
                    boxText = boxText.replace('Find prev','');
                    boxText = $.trim(boxText.replace(/\|\s+\d+\s+\W+/,''));
                    console.log(boxText);
                    var type = /^[a-z0-9]{7}\s+/.test(boxText) ? 'commit' : 'file';

                    var parent = c.getRelatedNodes($(this),'parent');
                    var parentCommit = parent.text().substr(0,6);

                    if(type == 'file'){
                        window.open('<?php echo $origin_location?>/commit/'+ parentCommit+'/'+boxText,'_blank');
                    }

                    if(type == 'commit'){

                        //var grandfather = c.getRelatedNodes(parent, 'parent');
                        //var grandfatherCommit = grandfather.text().substr(0,6);
                        parentFile = cleanFileString(parent.text());
                        window.open('<?php echo $origin_location?>/commit/'+ boxText.substr(0,6)+'/'+parentFile,'_blank');
                    }

                });


                var title,cssClass,title_full,split_title,count_line;
                var boxes = {};
                $('.title').each(function(i, obj) {

                    if(i === 0){
                        //close tree
                        //$(this).next().click();
                        $(this).parent().attr('id','main-commit');
                        //location.href = '#main-commit'
                    } else {

                        title = $(this).text();
                        title_full = title.replace('Show details from Github','').replace('Find prev','');
                        split_title = title_full.split('|');
                        title = $.trim(split_title[0]);
                        count_line = typeof split_title[1] !== 'undefined' ? parseInt($.trim(split_title[1].replace(/\++\-+/,''))) : 0;

                        if(typeof boxes[title] == 'undefined'){
                            boxes[title] = {};
                            boxes[title].color = getRandomColor();
                            boxes[title].count = 1;
                            boxes[title].lines_changed = count_line;
                            cssClass= boxes[title].color.substr(1,boxes[title].color.length);

                            $(this).css('background', boxes[title].color);
                            $(this).parent().addClass(cssClass);
                            $(this).parent().attr('id',cssClass+boxes[title].count);
                            $(this).parent().attr('title',title_full);
                        } else {
                            boxes[title].count++;
                            boxes[title].lines_changed = boxes[title].lines_changed + count_line;
                            cssClass= boxes[title].color.substr(1,boxes[title].color.length);
                            $(this).parent().addClass(cssClass);
                            $(this).parent().attr('id',cssClass+boxes[title].count);
                            $(this).css('background', boxes[title].color);

                            $(this).append('<button class="find-prev">Find prev</button>')
                            $(this).parent().attr('title',title_full);
                        }
                    }
                   // console.log(boxes);

                });

                // Charts for count of lines are changed in every file
                var stat_files = [];
                var object;
                $.each(boxes, function (key, element) {

                    if(element.lines_changed > 0){
                        stat_files.push({label:key,y:parseInt(element.lines_changed)});
                    }

                });
                console.log(stat_files);

                var chart = new CanvasJS.Chart("chartContainer", {
                    theme: "light1", // "light1", "light2", "dark1", "dark2"
                    title:{
                        text: "Count lines are changed in every file"
                    },
                    height: 1000,
                    width: 1200,
                    axisX:{
                        labelFontSize: 20
                    },
                    axisY:{
                        labelFontSize: 20
                    },
                    data: [{
                        type: "column", //change type to bar, line, area, pie, etc
                        dataPoints: stat_files
                    }]
                });
                chart.render();
                chart.title.set("fontSize", 20);


                var mainFiles = c.getRelatedNodes($('#main-commit'), 'children');
                var mainFilesCountLinesStat = [];
                var mainFilesCountCommitsStat = [];
                var fileName, fileLIneChanged;
                var totalStat = 0;
                $.each(mainFiles,function (key, file) {
                    fileName = cleanFileString(file.title);
                    fileLIneChanged = $.trim(file.title.match(/\s+\d+\s+/));
                    mainFilesCountCommitsStat.push({label:fileName, y: boxes[fileName].count})
                    mainFilesCountLinesStat.push({label:fileName, y: boxes[fileName].lines_changed})
                    totalStat = totalStat + difficultyAlgorithm(boxes[fileName].lines_changed ,boxes[fileName].count, fileLIneChanged);
                });

                console.info(totalStat);


                var chart = new CanvasJS.Chart("chartMainContainer", {
                    exportEnabled: true,
                    animationEnabled: true,
                    title:{
                        text: "Information about a required commit",
                        fontSize:40
                    },
                    subtitles: [{
                        text: "Important info about files are changed in the required commit"
                    }],
                    axisX: {
                        title: "Files",
                        labelFontSize: 25
                    },
                    axisY: {
                        title: "Count lines",
                        titleFontColor: "#4F81BC",
                        lineColor: "#4F81BC",
                        labelFontColor: "#4F81BC",
                        tickColor: "#4F81BC"
                    },
                    axisY2: {
                        title: "Count commits",
                        titleFontColor: "#C0504E",
                        lineColor: "#C0504E",
                        labelFontColor: "#C0504E",
                        tickColor: "#C0504E",
                    },
                    toolTip: {
                        shared: true
                    },
                    legend: {
                        cursor: "pointer",
                        itemclick: toggleDataSeries
                    },
                        height: 800,
                        width: 800,
                    data: [{
                        type: "column",
                        name: "lines",
                        showInLegend: true,
                        yValueFormatString: "#,##0.# lines",
                        dataPoints: mainFilesCountLinesStat
                    },
                    {
                        type: "column",
                        name: "commits",
                        axisYType: "secondary",
                        showInLegend: true,
                        yValueFormatString: "#,##0.# commits",
                        dataPoints: mainFilesCountCommitsStat
                    }]
                });
                chart.render();

                var level = 0;
                switch (true){
                    case  (totalStat <= 20):
                        level = 15;
                        break;
                    case (totalStat > 20 && totalStat <=80):
                        level = 45;
                        break;
                    case (totalStat > 80 && totalStat <=250):
                        level = 75;
                        break;
                    case (totalStat > 250 && totalStat <= 500):
                        level = 105;
                        break;
                    case (totalStat > 500 && totalStat <=800):
                        level = 145;
                        break;
                    case (totalStat > 800 ):
                        level = 165;
                        break;
                }


// Trig to calc meter point
                var degrees = 180 - level,
                    radius = .5;
                var radians = degrees * Math.PI / 180;
                var x = radius * Math.cos(radians);
                var y = radius * Math.sin(radians);

// Path: may have to change to create a better triangle
                var mainPath = 'M -.0 -0.025 L .0 0.025 L ',
                    pathX = String(x),
                    space = ' ',
                    pathY = String(y),
                    pathEnd = ' Z';
                var path = mainPath.concat(pathX,space,pathY,pathEnd);

                var data = [{ type: 'scatter',
                    x: [0], y:[0],
                    marker: {size: 28, color:'850000'},
                    showlegend: false,
                    name: 'speed',
                    text: level
                    },
                    { values: [50/6, 50/6, 50/6, 50/6, 50/6, 50/6, 50],
                        rotation: 90,
                        text: ['TOO HARD!', 'Pretty Hard', 'Hard', 'Average',
                            'Easy', 'Super Easy',''],
                        textinfo: 'text',
                        textposition:'inside',
                        marker: {colors:['rgba(233, 20, 20, 0.8)', 'rgba(255, 106, 0, 0.5)',
                            '#edc70b', '#fffa90',
                             'rgba(167, 210, 145, .5)',
                            'rgba(68, 157, 68, 0.8)', 'rgba(255, 255, 255, 0)']},
                        labels: ['A dangerous amount of changes and dependence files', 'Too mach changes and dependence files', 'A big amount of changes and dependence files', 'Possible amount of files and changes', 'A little files and code lines', 'Very little files and code lines', ''],
                        hoverinfo: 'label',
                        hole: .5,
                        type: 'pie',
                        showlegend: false
                    }];

                var layout = {
                    shapes:[{
                        type: 'path',
                        path: path,
                        fillcolor: '850000',
                        line: {
                            color: '850000'
                        }
                    }],
                    title: 'Gauge difficulty',
                    height: 1000,
                    width: 1000,
                    xaxis: {zeroline:false, showticklabels:false,
                        showgrid: false, range: [-1, 1]},
                    yaxis: {zeroline:false, showticklabels:false,
                        showgrid: false, range: [-1, 1]}
                };

                Plotly.newPlot('gauge-charts', data, layout,{displayModeBar: false});



                function toggleDataSeries(e) {
                    if (typeof (e.dataSeries.visible) === "undefined" || e.dataSeries.visible) {
                        e.dataSeries.visible = false;
                    } else {
                        e.dataSeries.visible = true;
                    }
                    e.chart.render();
                }



                setTimeout(function () {
                    var allClass, allClassSplit, selectedClass;
                    $('.find-prev').on('click', function () {
                         allClass = $(this).parent().parent().attr('class');
                         allClassSplit = allClass.split(' ');
                         selectedClass = allClassSplit[1];
                        console.log(selectedClass);
                        $('.'+selectedClass).css('border','red solid 2px');

                        location.href = '#'+selectedClass+'1';

                        $('html, body').animate({
                            scrollTop: $('#'+selectedClass+'1').offset().top
                        }, 2000);


                    });
                    $('.node').tooltip();
                },0)

                function closeTree() {

                    c.hideChildren($('#main-commit'));
                }

                $('#chart-container').append('<button id="go-to-main" onclick="location.href = \'#main-commit\'">Go to main commit</button>')
                $('#chart-container').append('<button id="close-tree" >Close tree graph</button>')

                $('#close-tree').on('click', closeTree);

                document.getElementById("defaultOpen").click();

            }
        });

       /* $('.node').on('click', function () {
            var parent = c.getRelatedNodes($(this),'parent');
            var grandfather = c.getRelatedNodes(parent, 'parent');
            console.log(grandfather.text());
        })*/

        function getRandomColor() {
            var letters = '0123456789ABCDEF';
            var color = '#';
            for (var i = 0; i < 6; i++) {
                color += letters[Math.floor(Math.random() * 16)];
            }
            return color;
        }

        function cleanFileString(string){
            return $.trim(string.replace('Show details from Github','').replace('Find prev','').replace(/\|\s+\d+\s+\W+/,''));
        }
        
        
        function difficultyAlgorithm(lines, count, countLinesInMainCommit) {

            if(count === 1){
                //no dependence commits
                return 5;
            }
            if(countLinesInMainCommit <= 10 ){
                // required changes is not large so reduce from weight of dependencies
                return (lines * count)/6
            }
            if(countLinesInMainCommit <= 30){
                // required changes is not large so reduce from weight of dependencies
                return (lines * count)/2
            }
            // default return lines * count file get rate about difficult
            return lines * count
        }


    });

    var openTab = function (evt, tabName) {
        // Declare all variables
        var i, tabcontent, tablinks;

        // Get all elements with class="tabcontent" and hide them
        tabcontent = document.getElementsByClassName("tabcontent");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }

        // Get all elements with class="tablinks" and remove the class "active"
        tablinks = document.getElementsByClassName("tablinks");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }

        // Show the current tab, and add an "active" class to the button that opened the tab
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";


    }

</script>
</body>
</html>
