// Help window functionality - currently no-op
function help() {
	if ($("#dialog-help").length == 0) {
	var divTag = $('<div id="dialog-help" title="About Maslo"></div>');
	var text = "<h4>MASLO Help</h4><br/><br/>\
	\
	<h5>Making new projects</h5><br/>\
	In order to make a new project just click the +Add New Project button. Once you fill in the field with the name of your project click OK.\
    <br/><br/>\
	<h5>Navigation in MASLO</h5><br/>\
	The 'breadcrumb' trail at the top of the screen can be used to navigate within the authoring tool. Any blue text will function as a link and will return you to the location indicated if you click on it. For instance, if you click on My Content Packs from inside a specific pack, you will be returned to the list of packs you have created.\
	<br/><br/>\
	Adding and deleting content\
	In order to add a piece of content to the authoring tool just click the + Add Content button, and then select the content type you want to add. Upload allows you to upload Image, Audio, or Video files, Create Text allows you to author or paste in text content using a WYSIWYG editor, and Create Quiz allows you to create a quiz with multiple choice items. To delete content, just click the blue X on the far right of the list of items.\
	<br/><br/>\
	File types that work in MASLO</h5><br/>\
	The MASLO system can accept the following file types:<br/>\
	Image: .png, .jpg, .tiff, and .gif files including animated .gifs.<br/>\
	Audio: .mp3, .wav, and .aiff<br/>\
	Video: .mp4\
	<br/><br/>\
	<h5>Converting video files</h5><br/>\
	If you have a non .mp4 video file that you want to include in a content pack you will need to convert the file to .mp4 before adding it to MASLO. File types other than .mp4 cannot be guaranteed to play on both iOS and Android devices. The VLC media player offers one freely accessible way to convert files to .mp4, but there are other options available.\
	<br/><br/>\
	Recommendations for video file size</h5><br/>\
	Video files can rapidly expand the size of a content pack. In general larger files will eat up a users monthly data allowance if downloaded over a 3G connection, and if a pack exceeds 20MB, users will not be able to download it over 3G. Additionally, many videos are not shot with viewing on small screens in mind. For these reasons, it is advisable to minimize the use of video when possible and to use shorter smaller videos to ensure that learners will be able to access content packs.\
	<br/><br/>\
	<h5>Creating Math Content</h5><br/>\
	The current version of MASLO does not support the use of MathML to create math content. Although iOS and Android will render MathML, Adobe AIR does not. Developers are looking into a work around for implementing MathML in the authoring tool. In the meantime, mathematical expressions can be included in a content pack by rendering them as image files.";
	
	divTag.append(text);
	$("body").append(divTag);
	}
	$( "#dialog-help" ).dialog({
		height:600,
		width:550,
		modal: true,
		buttons: {			
			Close: function() {
				$( this ).dialog( "close" );
			}
		}
	});	
	return false;
}

