<?php
/***************************************************************************
 *   Copyright (C) 2005-2007 by Konstantin V. Arkhipov                     *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */

	/**
	 * Basis of all DAO's.
	 * 
	 * @ingroup DAOs
	**/
	abstract class GenericDAO extends Singleton implements BaseDAO
	{
		protected $identityMap	= array();
		
		protected $link			= null;
		protected $selectHead	= null;
		
		abstract public function getTable();
		abstract public function getObjectName();
		
		abstract public function makeOnlyObject(&$array, $prefix = null);
		abstract public function completeObject(
			Identifiable $object, &$array, $prefix = null
		);
		
		public function makeObject(&$array, $prefix = null)
		{
			return $this->completeObject(
				$this->makeOnlyObject($array, $prefix),
				$array,
				$prefix
			);
		}
		
		/**
		 * Returns link name which is used to get actual DB-link from DBPool,
		 * returning null by default for single-source projects.
		 * 
		 * @see DBPool
		**/
		public function getLinkName()
		{
			return null;
		}
		
		public function getIdName()
		{
			return 'id';
		}
		
		public function getSequence()
		{
			return $this->getTable().'_id';
		}
		
		/**
		 * @return AbstractProtoClass
		**/
		public function getProtoClass()
		{
			return call_user_func(array($this->getObjectName(), 'proto'));
		}
		
		public function getMapping()
		{
			return $this->getProtoClass()->getMapping();
		}
		
		public function getFields()
		{
			static $fields = array();
			
			$className = $this->getObjectName();
			
			if (!isset($fields[$className])) {
				$fields[$className] = array_values($this->getMapping());
			}
			
			return $fields[$className];
		}
		
		/**
		 * @return SelectQuery
		**/
		public function makeSelectHead()
		{
			if (null === $this->selectHead) {
				$table = $this->getTable();
				
				$this->selectHead = 
					OSQL::select()->
					from($table);
				
				foreach ($this->getFields() as $field)
					$this->selectHead->get(new DBField($field, $table));
			}
			
			return clone $this->selectHead;
		}
		
		/// boring delegates
		//@{
		public function getById($id, $expires = Cache::EXPIRES_MEDIUM)
		{
			if (isset($this->identityMap[$id]))
				return $this->identityMap[$id];
			
			return $this->addObjectToMap(
				Cache::worker($this)->getById($id, $expires)
			);
		}
		
		public function getByLogic(
			LogicalObject $logic, $expires = Cache::DO_NOT_CACHE
		)
		{
			return $this->addObjectToMap(
				Cache::worker($this)->getByLogic($logic, $expires)
			);
		}
		
		public function getByQuery(
			SelectQuery $query, $expires = Cache::EXPIRES_MEDIUM
		)
		{
			return $this->addObjectToMap(
				Cache::worker($this)->getByQuery($query, $expires)
			);
		}
		
		public function getCustom(
			SelectQuery $query, $expires = Cache::DO_NOT_CACHE
		)
		{
			return Cache::worker($this)->getCustom($query, $expires);
		}
		
		public function getListByIds(
			/* array */ $ids, $expires = Cache::EXPIRES_MEDIUM
		)
		{
			$mapped = $remove = array();
			
			foreach ($ids as $id) {
				if (isset($this->identityMap[$id])) {
					$mapped[] = $this->identityMap[$id];
					$remove[] = $id;
				}
			}
			
			foreach ($remove as $id)
				unset($ids[$id]);
			
			if ($ids) {
				$list = $this->addObjectListToMap(
					Cache::worker($this)->getListByIds($ids, $expires)
				);
				
				return array_merge($mapped, $list);
			}
			
			return $mapped;
		}
		
		public function getListByQuery(
			SelectQuery $query, $expires = Cache::DO_NOT_CACHE
		)
		{
			return $this->addObjectListToMap(
				Cache::worker($this)->getListByQuery($query, $expires)
			);
		}
		
		public function getListByLogic(
			LogicalObject $logic, $expires = Cache::DO_NOT_CACHE
		)
		{
			return $this->addObjectListToMap(
				Cache::worker($this)->getListByLogic($logic, $expires)
			);
		}
		
		public function getPlainList($expires = Cache::EXPIRES_MEDIUM)
		{
			return $this->addObjectListToMap(
				Cache::worker($this)->getPlainList($expires)
			);
		}
		
		public function getCustomList(
			SelectQuery $query, $expires = Cache::DO_NOT_CACHE
		)
		{
			return Cache::worker($this)->getCustomList($query, $expires);
		}
		
		public function getCustomRowList(
			SelectQuery $query, $expires = Cache::DO_NOT_CACHE
		)
		{
			return Cache::worker($this)->getCustomRowList($query, $expires);
		}
		
		public function getQueryResult(
			SelectQuery $query, $expires = Cache::DO_NOT_CACHE
		)
		{
			return Cache::worker($this)->getQueryResult($query, $expires);
		}
		
		public function drop(Identifiable $object)
		{
			$this->checkObjectType($object);
			
			return $this->dropById($object->getId());
		}
		
		public function dropById($id)
		{
			unset($this->identityMap[$id]);
			
			return Cache::worker($this)->dropById($id);
		}
		
		public function dropByIds(/* array */ $ids)
		{
			foreach ($ids as $id)
				unset($this->identityMap[$id]);
			
			return Cache::worker($this)->dropByIds($ids);
		}
		
		public function uncacheById($id)
		{
			unset($this->identityMap[$id]);
			
			return Cache::worker($this)->uncacheById($id);
		}
		
		public function uncacheByIds($ids)
		{
			foreach ($ids as $id)
				unset($this->identityMap[$id]);
			
			return Cache::worker($this)->uncacheByIds($ids);
		}
		
		public function uncacheLists()
		{
			return Cache::worker($this)->uncacheLists();
		}
		//@}
		
		/**
		 * @return GenericDAO
		**/
		public function dropIdentityMap()
		{
			$this->identityMap = array();
			
			return $this;
		}
		
		/* void */ protected function checkObjectType(Identifiable $object)
		{
			Assert::isTrue(
				get_class($object) === $this->getObjectName(),
				'strange object given, i can not inject it'
			);
		}
		
		private function addObjectToMap($object)
		{
			return $this->identityMap[$object->getId()] = $object;
		}
		
		private function addObjectListToMap($list)
		{
			foreach ($list as $object)
				$this->identityMap[$object->getId()] = $object;
			
			return $list;
		}
	}
?>