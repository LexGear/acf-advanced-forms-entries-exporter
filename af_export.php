<?php

function af_export_menu()
{
	add_submenu_page(
		'edit.php?post_type=af_form',
		'Export AF Entries',
		'Export Entries',
		'manage_options',
		'export-af-entries',
		'export_entries_page'
	);
}
add_action('admin_menu', 'af_export_menu');

function get_form_field_names( $fields )
{
	$fields_array = [];
	foreach ( $fields as $field )
	{
		if ( is_array( $field ) )
		{
			if ( array_key_exists( "type", $field ) && $field["type"] == "group" )
			{
				$sub_fields = get_form_field_names( $field["sub_fields"] );
				$fields_array = array_merge( $fields_array, $sub_fields );
			}
			else if ( array_key_exists( "name", $field ) && $field["name"] != "" )
			{
				array_push( $fields_array, $field["name"] );
			}
		}
	}

	return $fields_array;
}

function get_field_values( $fields )
{
	$values_array = [];

	foreach ( $fields as $field )
	{
		if ( is_array( $field ) )
		{
			$sub_values = get_field_values( $field );
			$values_array = array_merge( $values_array, $sub_values );
		}
		else if ( is_string( $field ) )
		{
			array_push( $values_array, $field );
		}
	}

	return $values_array;
}

function export_entries_page()
{
	echo '<h1>Export Entries</h1>';

	// form selector
	$forms = af_get_forms();
	echo '<form method="post" action="">';
	echo '<label>Select form:</label>';
	echo '<select name="url">';
	foreach ( $forms as $form )
	{
		$form_url = admin_url() . 'edit.php?post_type=af_form&page=export-af-entries&form_id=' . $form["key"];
		echo '<option value="' . $form_url . '">' . $form["title"] . '</option>';
	}
	echo '</select>';
	echo '<input type="submit" name="submit" value="Show Entries">';
	echo '</form>';
	echo '<hr>';

	if ( isset( $_POST['submit'] ) )
	{
		$url = $_POST['url'];
		header( "Location: $url" );
		exit();
	}

	// get form id
	$form_id = isset( $_GET["form_id"] ) ? $_GET["form_id"] : "";

	if ( $form_id )
	{
		// get all entries and fields
		$entries = af_get_entries( $form_id );
		$fields = af_get_form_fields( $form_id );
		$field_names = get_form_field_names( $fields );

		// create the table
		$table = '';
		if ( $entries )
		{
			$table .= '<table cellspacing="1" cellpadding="5" border="1">';

			// create table header
			$table .= '<tr><th>id</th><th>date</th>';
			foreach ( $field_names as $name )
			{
				$table .= '<th>' . $name . '</th>';
			}
			$table .= '</tr>';

			// create table content
			foreach ( $entries as $entry )
			{
				$table .= '<tr>';
				$table .= '<td>' . $entry->ID . '</td>';
				$table .= '<td>' . $entry->post_date . '</td>';

				$values = get_fields( $entry );
				$values_array = get_field_values( $values );
				foreach ( $values_array as $value )
				{
					$table .= '<td>' . $value . '</td>';
				}

				$table .= '</tr>';
			}
			$table .= '</table>';
		}

		// show the table
		echo $table;

		// create the csv
		$csv = html_to_csv( $table );
		$folder = '../entry_export/';
		$filename = $form_id . '-' .date('Y_m-d');
		if ( ! is_dir( $folder ) )
		{
			mkdir( $folder, 0755, true );
		}
		$fp = fopen( $folder . $filename . '.csv', 'w' );
		$rows = explode( "\n", $csv );
		foreach ( $rows as $row )
		{
			$fields = str_getcsv( $row );
			fputcsv( $fp, $fields );
		}
		fclose( $fp );
		
		// download link
		echo '<a class="download-af-entries-button" href="' . get_site_url() . '/entry_export/' . $filename . '.csv">Download</a>';
	}
}

function html_to_csv( $html )
{
	$dom = new DOMDocument();
	$dom->loadHTML($html);
	$table = $dom->getElementsByTagName('table')->item(0);
	$rows = $table->getElementsByTagName('tr');
	$csv = array();
	foreach ($rows as $row) {
		$cells = $row->getElementsByTagName('td');
		$csv_row = array();
		foreach ($cells as $cell) {
			$csv_row[] = $cell->textContent;
		}
		$csv[] = implode(',', $csv_row);
	}
	return implode("\n", $csv);
}
?>

<style>
	.download-af-entries-button
	{
		border: solid 2px #6ea0c2;
		padding: 10px;
		border-radius: 10px;
		margin-top: 50px;
		font-size: 20px;
		display: inline-block;
		text-decoration: none;
		color: #6ea0c2;
		transition: 0.5s;
	}
	.download-af-entries-button:hover
	{
		background: #6ea0c2;
		color: white;
	}
</style>