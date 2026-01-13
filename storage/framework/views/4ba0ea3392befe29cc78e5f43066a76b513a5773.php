<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <title>Mass Codes</title>

  <style>

    table {
        width: 100%;
        border-collapse: collapse;
        border-spacing: 0;
        margin-bottom: 20px;
      }
      
      table th,
      table td {
        padding: 10;
        background: #EEEEEE;
        text-align: left;
        border-bottom: 1px solid #FFFFFF;
        font-size: 14px;
      }
      
      table th {
        text-align: left;
        /* white-space: nowrap;        
        font-weight: normal; */
        border: 1px solid #ddd;
      }
      
      table td {
        text-align: left;
        border: 1px solid #ddd;
      }
      
     
    </style>
  </head>

  <body>
    <h4>Date : <?php echo e($date); ?></h4>
    <h4>Durée : <?php echo e($duree); ?> Days</h4>
    <table border="0" cellspacing="0" cellpadding="0" style="table-layout: fixed">
        <tr style="height:5px !important; padding:5px !important;">
          <th style="width:10% !important; height:5px !important; padding:5px !important;">Order</th>
          <th style="width:45% !important; height:5px !important; padding:5px !important;">Code</th>
        </tr>
        <?php for($i = 0; $i < count($codes); $i++): ?>
          <tr style="height:5px !important; padding:5px !important;">
            <td style="width:10% !important; height:5px !important; padding:5px !important;"><?php echo e(++$i); ?></td>
            <td style="width:45% !important; height:5px !important; padding:5px !important;"><?php echo e($codes[--$i]); ?></td>
          </tr> 
        <?php endfor; ?>
    </table>

    <h4>Coupon codes ARC Player :</h4>
    <table border="0" cellspacing="0" cellpadding="0" style="table-layout: fixed">
        <tr style="height:5px !important; padding:5px !important;">
          <th style="width:10% !important; height:5px !important; padding:5px !important;">Order</th>
          <th style="width:45% !important; height:5px !important; padding:5px !important;">Code</th>
        </tr>
        <?php for($j = 0; $j < count($coupons); $j++): ?>
          <tr style="height:5px !important; padding:5px !important;">
            <td style="width:10% !important; height:5px !important; padding:5px !important;"><?php echo e(++$j); ?></td>
            <td style="width:45% !important; height:5px !important; padding:5px !important;"><?php echo e($coupons[--$j]); ?></td>
          </tr> 
        <?php endfor; ?>
    </table>




  </body>
</html>

<?php /**PATH /home/admin/domains/newpanel.kingiptv.pro/project/resources/views/masscodes.blade.php ENDPATH**/ ?>