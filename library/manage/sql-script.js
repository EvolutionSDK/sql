sql = {
	sync: function() {
		$('.state-init').fadeOut(500, function(e) {
			$('.state-running').fadeIn(500, function(e) {
				$.post('/@manage/sql/sync', new Object(), function(r) {
					$('.state-running').fadeOut(500, function(e) {
						$('.state-complete').fadeIn(500);
						
						//sql.uptd();
					});
				});
			});
		})
	},
	uptd: function() {
		$('ul.categories').fadeOut(500, function(e) {
			$.post('/@manage/sql/uptd', new Object(), function(r) {
				$('ul.categories').html(r);
				setTimeout(function() {$('ul.categories').fadeIn(500);}, 500);
			});
		});
	}
};