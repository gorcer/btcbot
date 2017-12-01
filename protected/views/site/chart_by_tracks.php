<?php
/* @var $this SiteController */

$this->pageTitle=Yii::app()->name;


?>

<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"></script>
<script src="http://code.highcharts.com/stock/highstock.js"></script>

<div id="container" style="height: 500px; min-width: 500px"></div>

<?php 
Dump::d($tracks_origin);
?>

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
		        text: 'BTC-USD'
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
		    }, 

		    <?php foreach($tracks as $key => $track): ?>
	    	{
	        name: 'buy-<?php echo $key ?>',
	        data: <?php echo $track; ?>,
			id: 'dataseries',
			tooltip: {
				valueDecimals: 4
					}
	    	},
		<?php endforeach; ?>
	    	]
		    
		    
		});
	
});

</script>