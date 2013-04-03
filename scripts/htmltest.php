<?php

require "../base.php";
global $_base;

$_base->page->add_title("HTML testing");
access::no_guest();


if (!isset($_POST['hide']))
{
	$_base->page->add_js('
function checkTabEnter(e, textarea)
{
	var key = window.event ? e.keyCode : (e.keyCode ? e.keyCode : e.charCode);
	
	if (key == 13)
	{
		if (textarea.setSelectionRange)
		{
			var begin = textarea.value.substr(0, textarea.selectionStart);
			var end = textarea.value.substr(textarea.selectionEnd);
			
			// finn forrige \n før begin
			var tabs = "";
			var line_start = begin.lastIndexOf("\n");
			//if (line_start != -1) {
				var str_pos = line_start+1;
				while (begin.substr(str_pos++, 1) == "\t") {
					tabs += "\t";
				}
				end = tabs + end;
			//}
			
			var scrollTop = textarea.scrollTop;
			textarea.value = begin + "\n" + end;
			textarea.scrollTop = textarea.scrollTop;
			
			/*if (textarea.setSelectionRange) {
				textarea.focus();
				textarea.setSelectionRange(begin.length+tabs.length+1, begin.length+tabs.length+1);
			}*/
			return false;
		}
		else if (document.selection)
		{
			return true;
			/*
			var range = document.selection.createRange();
			if (range.parentElement() == textarea)
			{
				alert(range.text);
				
				
				var isCollapsed = range.text == \'\';
				range.text = replaceString;
				
				if (!isCollapsed)  {
				range.moveStart(\'character\', -replaceString.length);
				range.select();
				}
				}
			}*/
		}
	}
	
	else if (key == 9)
	{
		if (textarea.setSelectionRange)
		{
			var begin = textarea.value.substr(0, textarea.selectionStart);
			var end = textarea.value.substr(textarea.selectionEnd);

			textarea.value = begin + "\t" + end;

			if (textarea.setSelectionRange)
			{
				textarea.focus();
				textarea.setSelectionRange(begin.length + 1, begin.length + 1);
			}
		}
		else if (textarea.caretPos)
		{
			var caretPos = textarea.caretPos;
			caretPos.text = caretPos.text.charAt(caretPos.text.length - 1) == \' \' ? "\t " : "\t";
			//caretPos.select();
		}
		else
		{
			textarea.value += "\t";
			textarea.focus(textarea.value.length-1);
		}
		return false;
	}
}
');
	
	echo '
<h1>HTML-testing</h1>
<form action="" method="post">
	<div class="section">
		<h2>HTML verdi</h2>
		<p>
			<textarea name="content" cols="50" rows="15" style="width: 594px" onkeydown="return checkTabEnter(event, this)">'.htmlspecialchars(postval('content')).'</textarea>
		</p>
		<h3 class="c">
			'.show_sbutton("Vis resultat").'
			'.show_sbutton("Vis resultat UTEN denne boksen", 'name="hide"').'
		</h3>
	</div>
</form>';
}

if (isset($_POST['content']))
{
	echo $_POST['content'];
}

$_base->page->load();