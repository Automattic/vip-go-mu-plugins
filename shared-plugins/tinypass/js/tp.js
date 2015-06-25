if(typeof window.tpShowOfferCustom == 'undefined') {
	function tpShowOfferCustom(){
		try{	
			if(typeof window.getTPMeter == 'function'){
				var meter = getTPMeter();
				meter.showOffer();	
			}
		}catch(ex){}
	}
}
		

