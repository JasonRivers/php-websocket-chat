var myname='';

function showNamePanel() {


              UserInput= $('<input>')
		.attr({
			id:"name",
			maxlength:"15",
			name:"name",
			placeholder:"Your Name",
			type:"text",
			onkeydown:"if (event.keyCode == 13)document.getElementById('enableChatbtn').click()"
			});

usrnameDiv= $('<div></div>')
	.append('<p> Enter a username to enable the live chat</p>')
	.append(UserInput)
	.append('<button name="enableChat" id="enableChatbtn" class="button">Enable Chat</button>');

$('.chat_wrapper').append(usrnameDiv)


$('#enableChatbtn').click(chatInit);
	

}

function messageCheck(msg) {

	return msg.replace(/&/g, "&amp;").replace(/>/g, "&gt;").replace(/</g, "&lt;").replace(/"/g, "&quot;");

}

function chatInit() {

	myname=$('#name').val();

$('.chat_wrapper').html('<div class="message_box" id="message_box"></div><div class="panel"><input type="text" name="message" id="message" placeholder="Message" onkeydown = "if (event.keyCode == 13)document.getElementById(\'send-btn\').click()"  /> </div> <button id="send-btn" class=button>Send</button>');


        //Chat colours:
        var colours =['007AFF','FF7000','FF7000','15E25F','CFC700','CFC700','CF1100','CF00BE','F00'];
        var user_colour = (Math.random()*0xFFFFFF<<0).toString(16);

        //create a new WebSocket object.
        var wsUri = "ws://localhost:9000/server.php";         
        websocket = new WebSocket(wsUri); 
        
        websocket.onopen = function(ev) { // connection is open 
		var connectedmsg = {
		name: myname,
		type: "connected"
		};
		websocket.send(JSON.stringify(connectedmsg));
        }

        $('#send-btn').click(function(){ //use clicks message send button       
                var mymessage = $('#message').val(); //get message text
		// don't allow html characters - should prevent some access to the DOM

		mymessage=messageCheck(mymessage);
    //            var myname = $('#name').val(); //get user name
                
                if(myname == ""){ //empty name?
                        alert("Enter your Name please!");
                        return;
                }
                if(mymessage == ""){ //emtpy message?
                        alert("Enter Some message Please!");
                        return;
                }
                
                var objDiv = document.getElementById("message_box");
                objDiv.scrollTop = objDiv.scrollHeight;
                //prepare json data
                var msg = {
                message: mymessage,
                name: myname,
                color : user_colour
                };
                //convert and send data to server
                websocket.send(JSON.stringify(msg));
                $('#message').val(''); //reset text
        });

        //#### Message received from server?
        websocket.onmessage = function(ev) {
                var msg = JSON.parse(ev.data); //PHP sends Json data
                var type = msg.type; //message type
                var umsg = msg.message; //message text
                var uname = msg.name; //user name
                var ucolor = msg.color; //color
		console.log (ev);

                if(type == 'usermsg') 
                {
		if (uname != "null" && uname != null && umsg != "null" && umsg != null) {
                        $('#message_box').append("<div><span class=\"user_name\" style=\"color:#"+ucolor+"\">"+uname+"</span> : <span class=\"user_message\">"+umsg+"</span></div>");
		}
                }
                if(type == 'system')
                {
                        $('#message_box').append("<div class=\"system_msg\">"+umsg+"</div>");
                }
		if(type == 'history')
		{
                        $('#message_box').append("<div><span class=\"user_name\" style=\"color:#"+ucolor+"\">"+uname+"</span> : <span class=\"history_msg\">"+umsg+"</span></div>");
		}
                
                
                var objDiv = document.getElementById("message_box");
                objDiv.scrollTop = objDiv.scrollHeight;
        };
        
        websocket.onerror       = function(ev){$('#message_box').append("<div class=\"system_error\">Error Occurred - "+ev.data+"</div>");}; 
        websocket.onclose       = function(ev){$('#message_box').append("<div class=\"system_msg\">Connection Closed</div>");}; 

}

$(document).ready(function(){

if (chatEnabled === "true") {
//$('#VideoList').hide();

//myname = "Jason";

if (myname == "") {
	showNamePanel();
} else {

chatInit();

}

}

});

