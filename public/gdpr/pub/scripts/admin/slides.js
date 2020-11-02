 var slidesAdmin = {
	
	forOnload: function(){
		
		$("#slide_form a.adds").click(slidesAdmin.slideAddClick);   		
		$("#slide_form a.addq").click(slidesAdmin.questionAddClick);		
		$("#slide_form a.adda").click(slidesAdmin.answerAddClick);
		$("#slide_form a.dels").click(slidesAdmin.slideDeleteClick);   		
		$("#slide_form a.delq").click(slidesAdmin.questionDeleteClick);		
		$("#slide_form a.dela").click(slidesAdmin.answerDeleteClick);
		
	},
	
	slideDeleteClick: function(e) {
	    e.preventDefault();
		if (confirm('Are you sure you want to delete this slide?')) {
             $.post(this.href, {}, function(json) {
                var object = JSON.parse(json);
                if (object.deleted) {
                    $('#slide_'+ object.slide_id).remove();
                } else {
                    alert('Unable to delete slide!');
                }
            });
        } else {
            return false;
        }
		
	},
	
	questionDeleteClick: function(e) {
	     e.preventDefault();
         if (confirm('Are you sure you want to delete this question?')) {
             $.post(this.href, {}, function(json) {
                var object = JSON.parse(json);
                if (object.deleted) {
                    $('#question_'+ object.question_id).remove();
                } else {
                    alert('Unable to delete question!');
                }
            });
         } else {
             return false;
         }
		
	},
	
	answerDeleteClick: function(e) {
	    e.preventDefault();
        if (confirm('Are you sure you want to delete this answer?')) {
		 $.post(this.href, {}, function(json) {			 						
			var object = JSON.parse(json);
			if (object.deleted) {				
				$('#answer_'+ object.answer_id).remove();  
			} else {
				alert('Unable to delete answer!');
			}							
		});
        } else {
            return false;
        }
		
	},	
	
	slideAddClick: function(e) {
	     e.preventDefault();			 
		 
		 $.post(this.href, {}, function(json) {			 						
			var object = JSON.parse(json);
			$(e.target.parentNode.parentNode).before(object.data);			
			$('#slide_'+ object.slide_id +' a.addq').click(slidesAdmin.questionAddClick);
			$('#slide_'+ object.slide_id +' a.dels').click(slidesAdmin.slideDeleteClick);
		});
        return false;
	},
	
	questionAddClick: function(e) {
	     e.preventDefault();			 
		 
		 $.post(this.href, {}, function(json) {			 						
			var object = JSON.parse(json);
			$('#slide_'+object.slide_id).append(object.data);
             adminBasic.bootstrapSelect();
			$('#question_'+ object.question_id +' a.adda').click(slidesAdmin.answerAddClick);	
			$('#question_'+ object.question_id +' a.delq').click(slidesAdmin.questionDeleteClick);							
		});
        return false;
	},
	
	answerAddClick: function(e) {
	     e.preventDefault();			 
		 
		 $.post(this.href, {}, function(json) {			 						
			
			var object = JSON.parse(json);
			$('#question_'+object.question_id).append(object.data);
            adminBasic.bootstrapSelect();
			$('#answer_'+ object.answer_id +' a.dela').click(slidesAdmin.answerDeleteClick);
		});
        return false;
	}
	 
 };
 		
addLoadEvent('slidesAdmin.forOnload');		