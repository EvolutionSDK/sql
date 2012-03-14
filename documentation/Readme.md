About the SQL Bundle
====================
The sql bundle contains modeling, structure synchronizing, and advanced querying abilities as well as SQL injection protection.

Stack structure
===============

	* Bundle
	 * Connection
	  * Query
	   * Result
	  * Architecture
	 * Model
	  * List
	

Standard Usage
==============
To run manual queries it is as simple as this.

	e::$sql->query("QUERY");

We also have several query shortcuts designed to help you write simpiler, prettier queries.

	e::$sql()
		->select($table, $conditions = '');
		->select_by_id($table, $id);
		->insert($table, $array);
		->replace($table, $array);
		->update($table, $array, $conditions = '');
		->update_by_id($table, $array, $id);
		->delete($table, $conditions = '');
		->delete_by_id($table, $id);

All of the above methods will return a query object to help with interacting with the results.

Query Object
============
The query object is a php class to help with the returning of the SQL data. Depending on the query different features of the object may or may not work as expected.

On an insert pull the id of the row that was inserted (primary key)

	$results->insertId();

Return an all the affected rows (by default returns an associative array).

	$results->all($type = 'assoc|num/object');

Return the first row in the result set (by default returns an associative array).

	$results->row($type = 'assoc|num/object');

Return a List Model for the results (if one exists. **Not currently working. Deprecate as is not compatible with system?**)

	$results->lists();

Return a model for the first result (if one exists. **Not currently working. Deprecate as is not compatible with system?**)

	$results->model();

Return the total number of affected rows

	$results->count();

	
Architect
=========
Architect is run automatically when triggered in the E3 manager. The E3 Manager then syncs the sql yaml structure files with the database.

The SQL Structure file is as follows

	book:
		singular: book
		plural: books
		fields:
			title: string
			description: text
		hasOne:
			- author

	author:
		singular: author
		plural: authors
		fields:
			name: string

	shelf:
		singular: shelf
		plural: shelves
		fields:
			category: string
	  	manyToMany:
			- store
		hasMany:
			- book
	    
	store:
		singular: store
		plural: stores
		fields:
			name: string
			address: text
		manyToMany:
			- shelf

## Breaking down the SQL architecture file.

### The table name

	book:

The opening "book:" array is the name of the table which will be prefixed by the bundle name. So if your bundle is called "Inventory" then the name of this table once parsed and built would be "inventory.book"

	book:
		singular: book
		plural: books

The labels right after the opening "book:" statement are the singular and plural names. These names have nothing to do with how the database is generated but rather how the data is accessed within the bundle, and its family. Details of how this affects use will be describer later in this readme.

### Table Fields

	fields:
		title: string
		description: text

The "fields:" array is the a list of the columns within the database where the key is the column name and the value is either an array of column settings, or a column type. Note: id, created_timestamp, and updated_timestamp are created automatically by the architecture engine. This can be disbaled and will be described more in "Advanced Fields". The predefined column types are.

	string 		= varchar(255)
	text 		= text
	date 		= datetime
	bool 		= tinyint(1)
	number 		= int(11)
	money 		= decimal(10,2)
	decimal 	= decimal(10,3)

If you would like to use a value that is not listed above thats ok everything degrades to started SQL types. for example this ENUM is valid.

	fields:
		gender: enum('male','female')

### Advanced Fields

Suppose you want to set a default value for a column, or make a unique key, or allow null as a value. The E3 SQL Bundle uses the SQL "DESCRIBE;" row format for formatting tables. All you have to do is make your field look like this.

	fields:
		id:
			Type: number
			Null: NO
			Key: PRI
			Default: NULL
			Extra: auto_increment

Now of course as was mentioned above. ID is generated automatically by the architecture program there would be no reason to add this field to your sql_architecture.yaml file.

Sometimes you cannot have a primary key inside your table, or you really don't care when the row was created you can disable these extra fields by passing "_suppress" as the type.

	fields:
		id: _suppress
		created_timestamp: _supress
		title: string
		description: text

Now the table structure would have three columns "updated_timestamp", "title", and "description".

### Relationships

The E3 SQL Bundle has support for 3 styles of row relationships being hasOne, hasMany, and manyToMany. If you declare a hasOne it will create a hasMany on the other table and vice-versa. This may seem like an issue but infact it works perfectly. manyToMany will create another connections table in which the connection data will be stored.

Relationships are declared in the sql_structure.yaml file as follows.

	hasOne:
		- author
	hasMany:
		- shelf
	manyToMany:
		- store

If you want to create a relationship on another bundle just use the whole `bundle.table` name.

	hasOne:
		- members.account

You can declare an infinite amount of relationships and the architecture class will compensate for the changes.

Using Models (On a Bundle)
==========================
After creating your sql_structure.yaml file you are only 1 step away from having a functional modeling, and listing solution for your bundle.

## Setting up your Bundle class.

All you have to do is extend your bundle like so.

	namespace Bundles\Inventory;
	use Bundles\SQL\SQLBundle;
	use Exception;
	use e;

	class Bundle extends SQLBundle {

	}

Make sure you remember to add the use statement above.

Once you have done that all your sql tables are already accessable from the bundle class no more work required! WOOHOO!

## Using your database models

### Creating your first row

	$book = e::$inventory->newBook();	# Outside Bundles\Inventory\Bundle
	$book = $this->newBook();			# Within Bundles\Inventory\Bundle
	$book->title = "My Awesome Book";
	$book->save();

Bam thats it! If you look inside your "inventory.book" table in your database you should see a new row with the book title of "My Awesome Book".

### Accessing an existing row

	$book = e::$inventory->getBook(1);	# Outside Bundles\Inventory\Bundle
	$book = $this->getBook(1);			# Within Bundles\Inventory\Bundle
	echo $book->title 					# Returns: "My Awesome Book"

Thats all there is to it.

## Using relationships with your database models

Sometimes you want two different fields to work together. With our relationship system its super easy.

	$book = e::$inventory->newBook();	# Outside Bundles\Inventory\Bundle
	$book = $this->newBook();			# Within Bundles\Inventory\Bundle
	$book->title = "My Awesome Book";
	$book->save();

	$author = e::$inventory->newAuthor();	# Outside Bundles\Inventory\Bundle
	$author = $this->newAuthor();			# Within Bundles\Inventory\Bundle
	$author->name = "Kelly Becker";
	$author->save();

	$book->linkAuthor($author);

Now that you have attached a author to your book you can run

	$book = e::$inventory->getBook(1);	# Outside Bundles\Inventory\Bundle
	$book = $this->getBook(1);			# Within Bundles\Inventory\Bundle
	echo $book->getAuthor()->name;		# Returns: "Kelly Becker"

If you want to get a linked model on another bundle use the format of `$book->getBundleSingular()` or `$book->getBundlePlural()` respectively. So if instead of an authors table I used a members table I could run.

	$book = e::$inventory->getBook(1);	# Outside Bundles\Inventory\Bundle
	$book = $this->getBook(1);			# Within Bundles\Inventory\Bundle
	echo $book->getMembersAccount()->name();		# Returns: "Kelly Becker"

Multiple Connections
====================
Currently there is only limited support for multiple database connections within E3.
	
	e::$sql(slug)->query("QUERY");
	
