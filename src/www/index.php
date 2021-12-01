<?php

include_once('config.inc');
include_once('lib.inc');


$mysqli = open_db($db_ip, $db_user, $db_password, $db_name);

echo '<html>
<title>VPN Monitor</title>
<link type="text/css" rel="stylesheet" href="/assets/css/style.css" />
<link type="text/css" rel="stylesheet" href="/assets/css/bootstrap.min.css" />
<link type="text/css" rel="stylesheet" href="https://use.fontawesome.com/releases/v5.13.0/css/all.css" />
<script type="text/javascript" src="/assets/js/jquery-2.2.0.min.js"></script>
<script type="text/javascript" src="/assets/js/bootstrap.min.js"></script>
<body>
<div class="page_title">Online VPN Clients</div>';

$servers = get_vpn_servers();
echo '<div class="customer_filter">';
$sel_html = '<select name="customer_id" id="customer_id" class="custom-select blue-text">';
$sel_html .= '<option selected value="all">All</option>';
foreach($servers as $server)
{
  $sel_html .= '<option value="' . $server['id'] . '">' . $server['name'] . '&nbsp;&nbsp;&nbsp(' . $server['vpn_server_port'] . ')</option>';
}

$sel_html .= '</select>';
echo $sel_html;
echo '</div>';
echo '<div class="table_title">Devices</div>';
echo '<div id="client_list"><table id="client_devices" class="online">
  <tr class="table_headers"><td>#&nbsp;&nbsp;&nbsp;</td><td>VPN Client Name</td><td>VPN IP</td><td>Expiry</td>
  <td>Public IP</td><td class="description">Description</td><td>Customer Name</td><td class="edit_device_col">Edit</td></tr>';
echo '</table>';
echo '<div id="device_pagination" class="device_pagination" style="display:none;">';
echo '<form id="page_form" style="display:none;">
  <input type="hidden" name="devices_page_num" id="devices_page_num" value="1"/>
  </form>';
echo '<ul><li id="device_pagination_text"></li><li><a href="#" id="device_pagination_newer"><i class="fas fa-arrow-circle-left"></i></a></li>
      <li><a href="#" id="device_pagination_older"><i class="fas fa-arrow-circle-right"></i></a></li>
      </ul>';
echo '</div>';
echo '</div>';

echo '<div id="online_servers" style="display:none;"><br/><br/><div class="table_title">Servers</div>';
echo '<table id="client_servers" class="online">
  <tr class="table_headers"><td>#&nbsp;&nbsp;&nbsp;</td><td>VPN Client Name</td><td>VPN IP</td><td>Expiry</td>
  <td>Public IP</td><td class="description">Description</td><td>Customer Name</td><td class="edit_device_col">Edit</td></tr>';
echo '</table></div>';

echo '<div id="online_users" style="display:none;"><br/><br/><div class="table_title">Users</div>';
echo '<table id="client_users" class="online">
  <tr class="table_headers"><td>#&nbsp;&nbsp;&nbsp;</td><td>VPN Client Name</td><td>VPN IP</td><td>Expiry</td>
  <td>Public IP</td><td class="description">Description</td><td>Customer Name</td><td class="edit_device_col">Edit</td></tr>';
echo '</table></div>';
echo '<div style="padding-bottom: 25px;"></div>';

echo '<div class="text-center modal-btn">
  <div class="modal fade" id="loadEditClientModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <form class="form-horizontal" id="update_client" method="post" action="update_client.php">
        <input type="hidden" name="__csrf_magic" value="sid:060345a9e41d26511e475ed1e3f1e1f7cbe27346,1497506154">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="myModalLabel">Edit VPN Client</h4>
          </div>
          <div class="modal-body">
            <div class="popuperr_div"></div>
            <table class="popup_table">
            <tr><td class="row_label">Name:</td><td class="row_value"><input class="row_input_readonly" type="text" name="client_name" id="client_name" readonly /></td></tr>
            <tr><td class="row_label">Description:</td><td class=row_value"><input class="row_input_value" type="text" name="client_description" id="client_description" /></td></tr>
            <tr><td class="row_label"></td><td id="outer_notice"><span class="notice" id="notice"></span></td></tr>
            </table>
            <input type="hidden" name="customer_id" id="customer_id" />
            <input type="hidden" name="row_id" id="row_id" />
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            <button type="button" id="save" name="save" class="btn btn-primary" value="1">Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>';

echo "</body>
<script>
  window.addEventListener('load', function() {
    update_page();
  });

  $('#customer_id').change(function(){
    $('#devices_page_num').val(1);
    update_page();
  });

  function update_page(clear_devices = true, clear_servers = true, clear_users = true) {
    if (clear_devices) {
      $('.device_entry').remove();
    }

    if (clear_servers) {
      $('.server_entry').remove();
    }

    if (clear_users) {
      $('.user_entry').remove();
    }

    var customer_id = $('#customer_id').val();
    $.ajax({
      url: 'get_customer_clients.php',
      type: 'GET',
      data: {
        customer_id: customer_id,
        devices_page_num: $('#devices_page_num').val()
      },
      cache: false,
      success: function(data){
        data = JSON.parse(data);
        var devices_page_num = data['devices_page_num'];
        var devices_per_page = data['devices_per_page'];
        var devices_offset = (devices_page_num - 1) * devices_per_page;
        data['devices'].forEach(function (d, i){
          var tr_id = 'client_device_' + (i+1);
          var row = '<tr class=\"device_entry\" id=\"' + tr_id + '\"><td>' + (i+1+devices_offset) + '</td>';
          row += '<td>' + d['common_name'] + '</td>';
          row += '<td>' + d['ip'] + '</td>';
          row += '<td>' + d['cert_expiry'] + '</td>';
          row += '<td>' + d['public_ip'] + '</td>';
          row += '<td class=\"description\">' + d['description'] + '</td>';
          row += '<td>' + d['customer_name'] + '</td>';
          row += '<td><a href=\"#\" class=\"edit_device_cell\" data-toggle=\"modal\" data-target=\"#loadEditClientModal\" ' +
            'data-customer_id=\"' + d['customer_id'] + '\" data-client_name=\"' + d['common_name'] + '\" ' +
            'data-customer_name=\"' + d['customer_name'] + '\" data-client_description=\"' + d['description'] + '\" ' +
            'data-row_id=\"' + tr_id + '\" ' +
            '><i class=\"far fa-edit\"></i></a></td>';
          row += '</tr>';
          $('#client_devices tr:last').after(row);
        });

        update_pagination('devices', data['total_devices'], data['devices_page_num'], data['devices_per_page']);

        if (clear_servers) {
          if (data['servers'].length > 0) {
            data['servers'].forEach(function (d, i){
              var tr_id = 'client_server_' + (i+1);
              var row = '<tr class=\"server_entry\" id=\"' + tr_id + '\"><td>' + (i+1) + '</td>';
              row += '<td>' + d['common_name'] + '</td>';
              row += '<td>' + d['ip'] + '</td>';
              row += '<td>' + d['cert_expiry'] + '</td>';
              row += '<td>' + d['public_ip'] + '</td>';
              row += '<td class=\"description\">' + d['description'] + '</td>';
              row += '<td>' + d['customer_name'] + '</td>';
              row += '<td><a href=\"#\" class=\"edit_device_cell\" data-toggle=\"modal\" data-target=\"#loadEditClientModal\" ' +
                'data-customer_id=\"' + d['customer_id'] + '\" data-client_name=\"' + d['common_name'] + '\" ' +
                'data-customer_name=\"' + d['customer_name'] + '\" data-client_description=\"' + d['description'] + '\" ' +
                'data-row_id=\"' + tr_id + '\" ' +
                '><i class=\"far fa-edit\"></i></a></td>';
              row += '</tr>';
              $('#client_servers tr:last').after(row);
            });
            $('#online_servers').show();
          } else {
            $('#online_servers').hide();
          }
        }

        if (clear_users) {
          if (data['users'].length > 0) {
            data['users'].forEach(function (d, i){
              var tr_id = 'client_user_' + (i+1);
              var row = '<tr class=\"user_entry\" id=\"' + tr_id + '\"><td>' + (i+1) + '</td>';
              row += '<td>' + d['common_name'] + '</td>';
              row += '<td>' + d['ip'] + '</td>';
              row += '<td>' + d['cert_expiry'] + '</td>';
              row += '<td>' + d['public_ip'] + '</td>';
              row += '<td class=\"description\">' + d['description'] + '</td>';
              row += '<td>' + d['customer_name'] + '</td>';
              row += '<td><a href=\"#\" class=\"edit_device_cell\" data-toggle=\"modal\" data-target=\"#loadEditClientModal\" ' +
                'data-customer_id=\"' + d['customer_id'] + '\" data-client_name=\"' + d['common_name'] + '\" ' +
                'data-customer_name=\"' + d['customer_name'] + '\" data-client_description=\"' + d['description'] + '\" ' +
                'data-row_id=\"' + tr_id + '\" ' +
                '><i class=\"far fa-edit\"></i></a></td>';
              row += '</tr>';
              $('#client_users tr:last').after(row);
            });
            $('#online_users').show();
          } else {
            $('#online_users').hide();
          }
        }
      },
      error: function(data){
      }
    });
  }


  function update_pagination(type, num_entries, page_num, page_limit)
  {
    console.log('type:');
    console.log(type);
    console.log('num_entries:');
    console.log(num_entries);
    console.log('page_num:');
    console.log(page_num);
    console.log('page_limit:');
    console.log(page_limit);

    var start, end, s;
    if (page_num > 1) {
      start = ((page_num - 1) * page_limit) + 1;
    } else {
      start = 1;
    }

    if (num_entries > page_limit) {
      end = page_limit * page_num;
      if (end > num_entries) {
        end = num_entries;
      }

      s = start + ' - ' + end + ' of ' + num_entries;
    } else {
      end = num_entries;
      s = start + ' - ' + end;
    }

    console.log('start:');
    console.log(start);
    console.log('end:');
    console.log(end);
    console.log('s:');
    console.log(s);

    if (type == 'devices') {
      if (num_entries > 0) {
        $('#device_pagination_text').text(s);
        $('#device_pagination').show();
      } else {
        $('#device_pagination').hide();
      }

      if (page_num > 1) {
        $('#device_pagination_newer').removeClass('disable-click');
        if ((page_num * page_limit) >= num_entries) {
          $('#device_pagination_older').addClass('disable-click');
        } else {
          $('#device_pagination_older').removeClass('disable-click');
        }
      } else {
        $('#device_pagination_newer').addClass('disable-click');
        if (num_entries > page_limit) {
          $('#device_pagination_older').removeClass('disable-click');
        } else {
          $('#device_pagination_older').addClass('disable-click');
        }
      }

      $('#devices_page_num').val(page_num);
    }
  }

  $('#device_pagination_newer').click(function() {
    var page_num = $('#devices_page_num').val();
    page_num--;
    $('#devices_page_num').val(page_num);
    update_page(true, false, false);
  });

  $('#device_pagination_older').click(function() {
    var page_num = $('#devices_page_num').val();
    page_num++;
    $('#devices_page_num').val(page_num);
    update_page(true, false, false);
  });

  $('#loadEditClientModal').on('show.bs.modal', function (event) {
    var a = $(event.relatedTarget);
    var customer_id = a.data('customer_id');
    var customer_name = a.data('customer_name');
    var name = a.data('client_name');
    var row_id = a.data('row_id');
    var description  = jQuery('#' + row_id).find('td:eq(5)').html();
    var modal = $(this);
    modal.find('#client_name').val(name);
    modal.find('#client_description').val(description);
    modal.find('#customer_id').val(customer_id);
    modal.find('#row_id').val(row_id);
    $('#notice').empty();
  })

  $('#save').click(function() {
    $('#notice').empty();
    var row_id = $('#row_id').val();
    var new_desc = $('#client_description').val();
    var data = $('#update_client').serializeArray();
    $.ajax({
      url: 'update_client.php',
      type: 'POST',
      data: data,
      dataType: 'json',
      cache: false,
      success: function(data){
        if (data.status === 'success') {
          $('#outer_notice span').removeClass('error');
          $('#outer_notice span').addClass('success');
          $('#notice').html('Client successfully updated!');
          setTimeout(function(){
            jQuery('#loadEditClientModal').modal('hide');
          }, 2000);

          jQuery('#' + row_id).find('td:eq(5)').html(new_desc);
        } else {
          $('#outer_notice span').removeClass('success');
          $('#outer_notice span').addClass('error');
          $('#notice').html(data.message);
        }
      }
    });
  });

</script>
</html>";
?>
