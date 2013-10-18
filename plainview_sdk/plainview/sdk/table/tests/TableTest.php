<?php

class TableTest extends \plainview\sdk\tests\TestCase
{
	public function test_sections()
	{
		$t = $this->table();
		$this->assertNotEquals( null, $t->caption() );
		$this->assertNotEquals( null, $t->head() );
		$this->assertNotEquals( null, $t->body() );
		$this->assertNotEquals( null, $t->foot() );
	}
	public function test_row_id()
	{
		$t = $this->table();

		// Without an ID
		$row = $t->head()->row();
		$this->assertEquals( null, $row->get_attribute( 'id' ) );

		// With an ID
		$id = 'test123';
		$row = $t->head()->row( $id );
		$this->assertEquals( $id, $row->get_attribute( 'id' ) );
	}

	public function test_cell_id()
	{
		$t = $this->table();
		$row = $t->body()->row();

		// Without an ID
		$cell = $row->td();
		$this->assertEquals( null, $cell->get_attribute( 'id' ) );

		// With an ID
		$id = 'test1234';
		$cell = $row->td( $id );
		$this->assertEquals( $id, $cell->get_attribute( 'id' ) );
	}

	public function test_count()
	{
		$t = $this->table();

		$this->assertEquals( 0, count( $t ) );
		$this->assertEquals( 0, count( $t->body() ) );
		// Create an anonymous row
		$this->assertEquals( 0, count( $t->body()->row() ) );

		// Create the row "first"
		$first = $t->body()->row( 'first' );
		$this->assertEquals( 2, count( $t ) );
		$this->assertEquals( 2, count( $t->body() ) );
		// Row has no cells
		$this->assertEquals( 0, count( $t->body()->row( 'first' ) ) );

		$first->td();
		// Row has one td cell.
		$this->assertEquals( 1, count( $t->body()->row( 'first' ) ) );

	}

	public function table()
	{
		return new \plainview\sdk\table\table;
	}
}
