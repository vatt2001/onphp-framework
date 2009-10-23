<?php
	/* $Id$ */
	
	final class CriteriaTest extends TestCase
	{
		public function testClassProjection()
		{
			$criteria =
				Criteria::create(TestUser::dao())->
				setProjection(
					Projection::chain()->add(
						ClassProjection::create('TestUser')
					)->
					add(
						Projection::group('id')
					)
				);
			
			$this->assertEquals(
				$criteria->toSelectQuery()->getFieldsCount(),
				count(TestUser::dao()->getFields())
			);
		}
		
		public function testAddProjection()
		{
			$criteria = Criteria::create(TestUser::dao());
			
			$this->assertEquals(
				$criteria->getProjection(),
				Projection::chain()
			);
			
			$criteria = Criteria::create(TestUser::dao())->
				addProjection(
					Projection::chain()
				);
			
			$this->assertEquals(
				$criteria->getProjection(),
				Projection::chain()
			);
			
			$criteria = Criteria::create(TestUser::dao())->
				addProjection(
					Projection::property('id')
				);
			
			$this->assertEquals(
				$criteria->getProjection(),
				Projection::chain()->
					add(
						Projection::property('id')
					)
			);
		}
		
		public function testSetProjection()
		{
			$criteria = Criteria::create(TestUser::dao())->
				setProjection(
					Projection::chain()->
						add(
							Projection::property('id')
						)
				);
			
			$this->assertEquals(
				$criteria->getProjection(),
				Projection::chain()->
					add(
						Projection::property('id')
					)
			);
			
			$criteria = Criteria::create(TestUser::dao())->
				setProjection(
					Projection::property('id')
				);
			
			$this->assertEquals(
				$criteria->getProjection(),
				Projection::chain()->
					add(
						Projection::property('id')
					)
			);
		}
		
		/**
		 * @dataProvider orderDataProvider
		**/
		public function testOrder($order, $expectedString)
		{
			$criteria = Criteria::create(TestUser::dao())->
				setProjection(
					Projection::property('id')
				)->
				addOrder($order);
			
			$this->assertEquals(
				$criteria->toDialectString(ImaginaryDialect::me()),
				'SELECT test_user.id FROM test_user ORDER BY '.$expectedString
			);
		}

		public function testValueObject()
		{
			$criteria =
				Criteria::create(TestUserWithContact::dao())->
				setProjection(
					Projection::property('id')
				)->
				add(
					Expression::eq('contacts.city', 1)
				);			

			$this->assertEquals(
				$criteria->toDialectString(ImaginaryDialect::me()),
				'SELECT test_user_with_contact.id FROM test_user_with_contact WHERE (test_user_with_contact.city_id = 1)'
			);

			$criteria =
				Criteria::create(TestUserWithContact::dao())->
				setProjection(
					Projection::property('id')
				)->
				add(
					Expression::eq('contacts.city.name', 'Moscow')
				);		

			$this->assertEquals(
				$criteria->toDialectString(ImaginaryDialect::me()),
				'SELECT test_user_with_contact.id FROM test_user_with_contact JOIN custom_table AS 3524772f_city_id ON (test_user_with_contact.city_id = 3524772f_city_id.id) WHERE (3524772f_city_id.name = Moscow)'
			);
		}

		public static function orderDataProvider()
		{
			return array(
				array(OrderBy::create('id'), 'test_user.id'),
				array(
					OrderChain::create()->
						add(OrderBy::create('id')->asc())->
						add(OrderBy::create('id')),
					'test_user.id ASC, test_user.id'
				),
				array(OrderBy::create('id')->asc(), 'test_user.id ASC'),
				array(OrderBy::create('id')->desc(), 'test_user.id DESC'),
				array(OrderBy::create('id')->nullsFirst(), 'test_user.id NULLS FIRST'),
				array(OrderBy::create('id')->nullsLast(), 'test_user.id NULLS LAST'),
				array(OrderBy::create('id')->asc()->nullsLast(), 'test_user.id ASC NULLS LAST'),
				array(OrderBy::create('id')->desc()->nullsFirst(), 'test_user.id DESC NULLS FIRST'),
				array(
					OrderBy::create(Expression::isNull('id'))->
						asc()->
						nullsFirst(),
					'((test_user.id IS NULL)) ASC NULLS FIRST'
				)
			);
		}
	}
?>