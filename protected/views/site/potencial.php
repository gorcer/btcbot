

<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"></script>
<script src="http://code.highcharts.com/stock/highstock.js"></script>

<div id="container" style="height: 500px; min-width: 500px"></div>

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
		        text: 'Potencial rate'
		    },

		    yAxis: {
		        title: {
		            text: 'Year %'
		        }
		    },

			series: [
			<?php foreach ($data as $pair=>$values): ?>
				{
		        name: '<?php echo $pair; ?>',
		        data: <?php echo json_encode($values); ?>,
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