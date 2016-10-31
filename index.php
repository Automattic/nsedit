<?php
include_once('includes/config.inc.php');
include_once('includes/session.inc.php');
include_once('includes/misc.inc.php');

global $errormsg, $blocklogin;
?>

<!DOCTYPE html>
<html>
<head>
    <title>NSEdit!</title>
    <link href="jquery-ui/themes/base/all.css" rel="stylesheet" type="text/css"/>
    <link href="jtable/lib/themes/metro/blue/jtable.min.css" rel="stylesheet" type="text/css"/>
    <link href="css/base.css" rel="stylesheet" type="text/css"/>
    <link href="css/custom.css" rel="stylesheet" type="text/css"/>
    <?php if ($menutype === 'horizontal') { ?>
    <link href="css/horizontal-menu.css" rel="stylesheet" type="text/css"/>
    <?php } ?>
    <script src="jquery-ui/external/jquery/jquery.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/core.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/widget.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/mouse.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/draggable.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/position.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/button.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/resizable.js" type="text/javascript"></script>
    <script src="jquery-ui/ui/dialog.js" type="text/javascript"></script>
    <script src="jtable/lib/jquery.jtable.min.js" type="text/javascript"></script>
    <script src="js/addclear/addclear.js" type="text/javascript"></script>
</head>

<?php
if ($blocklogin === TRUE) {

       echo "<h2>There is an error in your config!</h2>";
       echo "<a href=\"index.php\">Refresh</a>";
       exit(0);
}

?>
<body>
<div id="wrap">
    <div id="menu" class="jtable-main-container <?php if ($menutype === 'horizontal') { ?>horizontal<?php } ?>">
        <div class="jtable-title menu-title">
            <div class="jtable-title-text">
                .blog DNS Admin
            </div>
        </div>
    </div>
    <?php if (isset($errormsg)) {
        echo '<span style="color: red">' . $errormsg . '</span><br />';
    }
    ?>
    <div id="zones">
        <div style="display: none;" id="ImportZone"></div>
        <div style="display: none;" id="CloneZone"></div>
        <div class="tables" id="MasterZones">
            <div class="searchbar" id="searchbar">
                <input type="text" id="domsearch" name="domsearch" placeholder="Search...."/>
            </div>
        </div>
        <div class="tables" id="SlaveZones"></div>
    </div>
</div>
<script type="text/javascript">
window.csrf_token = '';

$(document).ready(function () {
    function csrfSafeMethod(method) {
        // these HTTP methods do not require CSRF protection
        return (/^(GET|HEAD|OPTIONS|TRACE)$/.test(method));
    }
    $.ajaxSetup({
        beforeSend: function(xhr, settings) {
            if (!csrfSafeMethod(settings.type) && !this.crossDomain) {
                xhr.setRequestHeader("X-CSRF-Token", window.csrf_token);
            }
        }
    });
});

function displayDnssecIcon(zone) {
    if (zone.record.dnssec == true) {
        var $img = $('<img class="clickme" src="img/lock.png" title="DNSSec Info" />');
        $img.click(function () {
            $("#dnssecinfo").html("");
            $.each(zone.record.keyinfo, function ( i, val) {
                if (val.dstxt) {
                    $("#dnssecinfo").append("<p><h2>"+val.keytype+"</h2><pre>"+val.dstxt+"</pre></p>");
                }
            });
            $("#dnssecinfo").dialog({
                modal: true,
                title: "DS-records for "+zone.record.name,
                width: 'auto',
                buttons: {
                    Ok: function() {
                        $( this ).dialog( "close" );
                    }
                }
            });
        });
        return $img;
    } else {
        return '<img class="list" src="img/lock_open.png" title="DNSSec Disabled" />';
    }
}

function displayExportIcon(zone) {
    var $img = $('<img class="list clickme" src="img/export.png" title="Export zone" />');
    $img.click(function () {
        var $zexport = $.getJSON("zones/?zoneid="+zone.record.id+"&action=export", function(data) {
            blob = new Blob([data.Record.zone], { type: 'text/plain' });
            var dl = document.createElement('a');
            dl.addEventListener('click', function(ev) {
                dl.href = URL.createObjectURL(blob);
                dl.download = zone.record.name+'.txt';
            }, false);

            if (document.createEvent) {
                var event = document.createEvent("MouseEvents");
                event.initEvent("click", true, true);
                dl.dispatchEvent(event);
            }
        });
    });
    return $img;
}

function displayContent(fieldName, zone) {
    return function(data) {
        if (typeof(zone) != 'undefined') {
            var rexp = new RegExp("(.*)"+zone);
            var label = rexp.exec(data.record[fieldName]);
            var lspan = $('<span>').text(label[1]);
            var zspan = $('<span class="lightgrey">').text(zone);
            return lspan.add(zspan);
        } else {
            var text = data.record[fieldName];
            if (typeof data.record[fieldName] == 'boolean') {
                text == false ? text = 'No' : text = 'Yes';
            }
            return $('<span>').text(text);
        }
    }
}

function getEpoch() {
    return Math.round(+new Date()/1000);
}

$(document).ready(function () {
    var $epoch = getEpoch();
/*
    $('#SlaveZones').jtable({
        title: 'Slave Zones',
        paging: true,
        pageSize: 20,
        sorting: false,
        messages: {
            addNewRecord: 'Add new slave zone',
            editRecord: 'Edit slave zone',
            noDataAvailable: 'No slave zones found',
            deleteConfirmation: 'This slave zone will be deleted. Are you sure?'
        },
        openChildAsAccordion: true,
        actions: {
            listAction: 'zones/?action=listslaves',
            updateAction: 'zones/?action=update',
            createAction: 'zones/?action=create',
            deleteAction: 'zones/?action=delete',
        },
        fields: {
            id: {
                key: true,
                type: 'hidden'
            },
            name: {
                title: 'Domain',
                width: '8%',
                display: displayContent('name'),
                edit: false,
                inputClass: 'domain',
                listClass: 'domain'
            },
            dnssec: {
                title: 'DNSSEC',
                width: '3%',
                create: false,
                edit: false,
                display: displayDnssecIcon,
                listClass: 'dnssec'
            },
            kind: {
                create: true,
                type: 'hidden',
                list: false,
                defaultValue: 'Slave'
            },
            masters: {
                title: 'Masters',
                width: '20%',
                display: function(data) {
                    return $('<span>').text(data.record.masters.join('\n'));
                },
                input: function(data) {
                    var elem = $('<input type="text" name="masters">');
                    if (data && data.record) {
                        elem.attr('value', data.record.masters.join(','));
                    }
                    return elem;
                },
                inputClass: 'masters',
                listClass: 'masters'
            },
            serial: {
                title: 'Serial',
                width: '10%',
                display: displayContent('serial'),
                create: false,
                edit: false,
                inputClass: 'serial',
                listClass: 'serial'
            },
            records: {
                width: '5%',
                title: 'Records',
                paging: true,
                pageSize: 20,
                edit: false,
                create: false,
                display: function (zone) {
                    var $img = $('<img class="list" src="img/list.png" title="Records" />');
                    $img.click(function () {
                        $('#SlaveZones').jtable('openChildTable',
                            $img.closest('tr'), {
                                title: 'Records in ' + zone.record.name,
                                openChildAsAccordion: true,
                                actions: {
                                    listAction: 'zones/?action=listrecords&zoneid=' + zone.record.id
                                },
                                fields: {
                                    name: {
                                        title: 'Label',
                                        width: '7%',
                                        display: displayContent('name'),
                                        listClass: 'name'
                                    },
                                    type: {
                                        title: 'Type',
                                        width: '2%',
                                        display: displayContent('type'),
                                        listClass: 'type'
                                    },
                                    content: {
                                        title: 'Content',
                                        width: '30%',
                                        display: displayContent('content'),
                                        listClass: 'content'
                                    },
                                    ttl: {
                                        title: 'TTL',
                                        width: '2%',
                                        display: displayContent('ttl'),
                                        listClass: 'ttl'
                                    },
                                    disabled: {
                                        title: 'Disabled',
                                        width: '2%',
                                        display: displayContent('disabled'),
                                        listClass: 'disabled'
                                    }
                                }
                            }, function (data) {
                                data.childTable.jtable('load');
                            })
                    });
                    return $img;
                }
            },
            exportzone: {
                title: '',
                width: '1%',
                create: false,
                edit: false,
                display: displayExportIcon,
                listClass: 'exportzone'
            }
        }
    });
*/
    $('#MasterZones').jtable({
        title: 'Master/Native Zones',
        paging: true,
        pageSize: 20,
        messages: {
            addNewRecord: 'Add new zone',
            editRecord: 'Edit zone',
            noDataAvailable: 'No zones found',
            deleteConfirmation: 'This zone will be deleted. Are you sure?'
        },
        toolbar: {
            hoverAnimation: true,
            hoverAnimationDuration: 60,
            hoverAnimationEasing: undefined,
            items: [
                /*{
                    icon: 'jtable/lib/themes/metro/add.png',
                    text: 'Import a new zone',
                    click: function() {
                        $('#ImportZone').jtable('showCreateForm');
                    }
                },
                {
                    icon: 'jtable/lib/themes/metro/add.png',
                    text: 'Clone a zone',
                    click: function() {
                        $('#CloneZone').jtable('showCreateForm');
                    }
                }*/
            ],
        },
        sorting: false,
        selecting: true,
        selectOnRowClick: true,
        selectionChanged: function (data) {
            var $selectedRows = $('#MasterZones').jtable('selectedRows');
            $selectedRows.each(function () {
                var zone = $(this).data('record');
                $('#MasterZones').jtable('openChildTable',
                    $(this).closest('tr'), {
                        title: 'Records in ' + zone.name,
                        messages: {
                            addNewRecord: 'Add to ' + zone.name,
                            noDataAvailable: 'No records for ' + zone.name
                        },
                        toolbar: {
                            items: [
                                {
                                    text: 'Search zone',
                                    click: function() {
                                        $("#searchzone").dialog({
                                            modal: true,
                                            title: "Search zone for ...",
                                            width: 'auto',
                                            buttons: {
                                                Search: function() {
                                                    $( this ).dialog( 'close' );
                                                    opentable.find('.jtable-title-text').text(opentableTitle + " (filtered)");
                                                    opentable.jtable('load', {
                                                        label: $('#searchzone-label').val(),
                                                        type: $('#searchzone-type').val(),
                                                        content: $('#searchzone-content').val()
                                                    });
                                                },
                                                Reset: function() {
                                                    $('#searchzone-label').val('');
                                                    $('#searchzone-type').val('');
                                                    $('#searchzone-content').val('');
                                                    $( this ).dialog( 'close' );
                                                    opentable.find('.jtable-title-text').text(opentableTitle);
                                                    opentable.jtable('load');
                                                    return false;
                                                }
                                            }
                                        });
                                    }
                                }
                            ],
                        },
                        paging: true,
                        sorting: true,
                        pageSize: 20,
                        openChildAsAccordion: true,
                        actions: {
                            listAction: 'zones/?action=listrecords&zoneid=' + zone.id,
                            createAction: 'zones/?action=createrecord&zoneid=' + zone.id,
                            deleteAction: 'zones/?action=deleterecord&zoneid=' + zone.id,
                            updateAction: 'zones/?action=editrecord&zoneid=' + zone.id
                        },
                        fields: {
                            domid: {
                                create: true,
                                type: 'hidden',
                                defaultValue: zone.id
                            },
                            id: {
                                key: true,
                                type: 'hidden',
                                create: false,
                                edit: false,
                                list: false
                            },
                            domain: {
                                create: true,
                                type: 'hidden',
                                defaultValue: zone.name
                            },
                            name: {
                                title: 'Label',
                                width: '7%',
                                sorting: true,
                                create: true,
                                display: displayContent('name', zone.name),
                                inputClass: 'name',
                                listClass: 'name'
                            },
                            type: {
                                title: 'Type',
                                width: '2%',
                                options: function() {
                                    zonename = new String(zone.name);
                                    if (zonename.match(/(\.in-addr|\.ip6)\.arpa/)) {
                                        return {
                                            'PTR': 'PTR',
                                            'NS': 'NS',
                                            'MX': 'MX',
                                            'TXT': 'TXT',
                                            'SOA': 'SOA',
                                            'A': 'A',
                                            'AAAA': 'AAAA',
                                            'CERT': 'CERT',
                                            'CNAME': 'CNAME',
                                            'LOC': 'LOC',
                                            'NAPTR': 'NAPTR',
                                            'SPF': 'SPF',
                                            'SRV': 'SRV',
                                            'SSHFP': 'SSHFP',
                                            'TLSA': 'TLSA',
                                        };
                                    }
                                    return {
                                        'A': 'A',
                                        'AAAA': 'AAAA',
                                        'CERT': 'CERT',
                                        'CNAME': 'CNAME',
                                        'LOC': 'LOC',
                                        'MX': 'MX',
                                        'NAPTR': 'NAPTR',
                                        'NS': 'NS',
                                        'PTR': 'PTR',
                                        'SOA': 'SOA',
                                        'SPF': 'SPF',
                                        'SRV': 'SRV',
                                        'SSHFP': 'SSHFP',
                                        'TLSA': 'TLSA',
                                        'TXT': 'TXT',
                                    };
                                },
                                display: displayContent('type'),
                                create: true,
                                inputClass: 'type',
                                listClass: 'type'
                            },
                            content: {
                                title: 'Content',
                                width: '30%',
                                create: true,
                                sorting: true,
                                display: displayContent('content'),
                                inputClass: 'content',
                                listClass: 'content'
                            },
                            ttl: {
                                title: 'TTL',
                                width: '2%',
                                create: true,
                                sorting: false,
                                display: displayContent('ttl'),
                                defaultValue: '<?php echo $defaults['ttl']; ?>',
                                inputClass: 'ttl',
                                listClass: 'ttl'
                            },
                            setptr: {
                                title: 'Set PTR Record',
                                width: '2%',
                                list: false,
                                create: true,
                                defaultValue: 'false',
                                inputClass: 'setptr',
                                listClass: 'setptr',
                                options: function() {
                                    return {
                                        '0': 'No',
                                        '1': 'Yes',
                                    };
                                },
                            },
                            disabled: {
                                title: 'Disabled',
                                width: '2%',
                                create: true,
                                sorting: false,
                                display: displayContent('disabled'),
                                defaultValue: '<?php echo $defaults['disabled'] ? 'No' : 'Yes'; ?>',
                                inputClass: 'disabled',
                                listClass: 'disabled',
                                options: function() {
                                    return {
                                        '0': 'No',
                                        '1': 'Yes',
                                    };
                                },
                            },
                        }
                    }, function (data) {
                        opentable=data.childTable;
                        opentableTitle=opentable.find('.jtable-title-text').text();
                        data.childTable.jtable('load');
                    });
            });
        },
        openChildAsAccordion: false,
        actions: {
            listAction: 'zones/?action=list',
            //createAction: 'zones/?action=create',
            //deleteAction: 'zones/?action=delete',
            updateAction: 'zones/?action=update'
        },
        fields: {
            id: {
                key: true,
                type: 'hidden'
            },
            name: {
                title: 'Domain',
                width: '8%',
                display: displayContent('name'),
                edit: false,
                inputClass: 'domain',
                listClass: 'domain'
            },
            dnssec: {
                title: 'DNSSEC',
                width: '3%',
                create: false,
                edit: false,
                display: displayDnssecIcon,
                listClass: 'dnssec'
            },
            kind: {
                title: 'Type',
                width: '20%',
                display: displayContent('kind'),
                options: {'Native': 'Native', 'Master': 'Master'},
                defaultValue: '<?php echo $defaults['defaulttype']; ?>',
                edit: false,
                inputClass: 'kind',
                listClass: 'kind'
            },
            template: {
                title: 'Template',
                options: <?php echo json_encode(user_template_names()); ?>,
                list: false,
                create: true,
                edit: false,
                inputClass: 'template'
            },
            nameserver: {
                title: 'Nameservers',
                create: true,
                list: false,
                edit: false,
                input: function(data) {
                    var $template = data.form.find('#Edit-template');
                    var ns_form = '<?php foreach($defaults['ns'] as $ns) echo '<input type="text" name="nameserver[]" value="'.$ns.'" /><br />'; ?>';
                    var $elem = $('<div id="nameservers">' + ns_form + '</div>');
                    $template.change(function() {
                        $.get('zones/?action=getformnameservers&template='+$template.val(), function(getdata) {
                            if (getdata != "") {
                                $("#nameservers").html(getdata);
                            } else {
                                $("#nameservers").html(ns_form);
                            }
                        });
                    });
                    return $elem;
                },
                inputClass: 'nameserver nameserver1'
            },
            serial: {
                title: 'Serial',
                width: '10%',
                display: displayContent('serial'),
                create: false,
                edit: false,
                inputClass: 'serial',
                listClass: 'serial'
            },
            exportzone: {
                title: '',
                width: '1%',
                create: false,
                edit: false,
                display: displayExportIcon,
                listClass: 'exportzone'
            }
        }
    });
    $('#ImportZone').jtable({
        title: 'Import zone',
        actions: {
            createAction: 'zones/?action=create'
        },
        fields: {
            id: {
                key: true,
                type: 'hidden'
            },
            name: {
                title: 'Domain',
                inputClass: 'domain'
            },
            kind: {
                title: 'Type',
                options: {'Native': 'Native', 'Master': 'Master'},
                defaultValue: '<?php echo $defaults['defaulttype']; ?>',
                edit: false,
                inputClass: 'type'
            },
            zone: {
                title: 'Zonedata',
                type: 'textarea',
                inputClass: 'zonedata'
            },
            owns: {
                title: 'Overwrite Nameservers',
                type: 'checkbox',
                values: {'0': 'No', '1': 'Yes'},
                defaultValue: 1,
                inputClass: 'overwrite_namerserver'
            },
            nameserver: {
                title: 'Nameservers',
                create: true,
                list: false,
                edit: false,
                input: function(data) {
                    var ns_form = '<?php foreach($defaults['ns'] as $ns) echo '<input type="text" name="nameserver[]" value="'.$ns.'" /><br />'; ?>';
                    var $elem = $('<div id="nameservers">' + ns_form + '</div>');
                    return $elem;
                },
                inputClass: 'nameserver nameserver1'
            },
        },
        recordAdded: function() {
            $("#MasterZones").jtable('load');
            $("#SlaveZones").jtable('load');
        }

    });

    $('#CloneZone').jtable({
        title: 'Clone zone',
        actions: {
            createAction: 'zones/?action=clone'
        },
        fields: {
            id: {
                key: true,
                type: 'hidden'
            },
            sourcename: {
                title: 'Source domain',
                options: function(data) {
                    return 'zones/?action=formzonelist&e='+$epoch;
                },
                inputClass: 'sourcename'
            },
            destname: {
                title: 'Domain',
                inputClass: 'destname'
            },
            kind: {
                title: 'Type',
                options: {'Native': 'Native', 'Master': 'Master'},
                defaultValue: '<?php echo $defaults['defaulttype']; ?>',
                edit: false,
                inputClass: 'type'
            },
        },
        recordAdded: function() {
            $("#MasterZones").jtable('load');
            $("#SlaveZones").jtable('load');
        }

    });

    $('#domsearch').addClear({
        onClear: function() { $('#MasterZones').jtable('load'); }
    });

    function searchDoms() {
        $('#MasterZones').jtable('load', {
            domsearch: $('#domsearch').val()
        });
        $('#SlaveZones').jtable('load', {
            domsearch: $('#domsearch').val()
        });
    }

    var stimer = 0;

    $('#changepw1, #changepw2').on('input', function(e) {
        if ($('#changepw1').val() != $('#changepw2').val()) {
            $('#changepwsubmit').prop("disabled",true);
        } else {
            $('#changepwsubmit').prop("disabled",false);
        }
    });

    $('#domsearch').on('input', function (e) {
        e.preventDefault();
        clearTimeout(stimer);
        stimer = setTimeout(searchDoms, 400);
    });

    $('#zoneadmin').click(function () {
        $('#MasterZones').show();
        $('#SlaveZones').show();
    });
});
</script>
</body>
</html>
