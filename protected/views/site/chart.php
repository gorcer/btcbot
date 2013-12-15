<?php
/* @var $this SiteController */

$this->pageTitle=Yii::app()->name;


$flags=", {
		        type: 'flags',
		        name: 'Flags on series',
		        data: [";
foreach ($orders as $order)
{
	$flags.="{
					x: ".(strtotime($order->close_dtm)*1000+4*60*60*1000).",
					title: '".substr($order->type, 0, 1)."'
				}, ";
}
$flags.="],
		        onSeries: 'dataseries',
		        shape: 'squarepin'
		    }";
?>

<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"></script>
<script src="http://code.highcharts.com/stock/highstock.js"></script>

<div id="container" style="height: 500px; min-width: 500px"></div>

Баланс (руб.): <?php echo $status['balance']; ?><br/>
Баланс (btc): <?php echo $status['balance_btc']; ?><br/>
Заработано (руб.): <?php echo $status['total_income']; ?><br/>
Общие активы (руб.): <?php echo $status['total_balance']; ?><br/>
<script>
$(function() {
	
		
		// Create the chart
		$('#container').highcharts('StockChart', {
		    chart: {
		    },

		    rangeSelector: {
		        selected: 1
		    },

		    title: {
		        text: 'BTC to RUR exchange rate'
		    },

		    yAxis: {
		        title: {
		            text: 'Exchange rate'
		        }
		    },

			series: [{
		        name: 'buy',
		        data: <?php echo $data_buy; ?>,
				id: 'dataseries',
				tooltip: {
					valueDecimals: 4
				}
		    }, {
		        name: 'sell',
		        data: <?php echo $data_sell; ?>,
				id: 'dataseries',
				tooltip: {
					valueDecimals: 4
				}
		    }, {
		        name: 'average',
		        data: <?php echo $data_avg; ?>,
				id: 'dataseries',
				tooltip: {
					valueDecimals: 4
				}
		    }
		     <?php echo $flags; ?>]
		    
		});
	
});

</script>