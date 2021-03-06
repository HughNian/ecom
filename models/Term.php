<?php
/**
 * Term AR class file.
 * 
 * @author Jin Hu <bixuehujin@gmail.com>
 */

class Term extends CActiveRecord {

	private $_vocabularyId;
	private $_vocabulary;
	
	/**
	 * @return Term
	 */
	public static function model($className = null, $vocabulary = null) {
		$instance = parent::model(get_called_class());
		if ($vocabulary) {
			$instance->_vocabulary = TermVocabulary::load($vocabulary);
		}
		return $instance;
	}
	
	public function tableName() {
		return 'term';
	}
	
	public function relations() {
		return array(
			'vacabulary'=>array(self::HAS_ONE, 'TermVacabulary', array('vid'=>'vid')),
		);
	}
	
	public function rules() {
		return array(
			array('name,vid', 'required'),
			array('description,weight', 'safe'),
		);
	}
	
	/**
	 * Returns the default vocabulary the current term related.
	 * Custom Term should override this method and return a TermVocabulary object.
	 * 
	 * @return TermVocabulary
	 */
	public function vocabulary() {
		if ($this->_vocabulary instanceof TermVocabulary && $this->_vocabulary->vid) {
			return $this->_vocabulary;
		}else {
			throw new CException('Using default vocabulary should override the vocabulary() method.');
		}
	}
	
	public  function getVocabularyId() {
		if ($this->_vocabularyId === null) {
			$t = $this->vocabulary();
			$this->_vocabularyId = $t->vid;
		}
		return $this->_vocabularyId;
	}
	
	/**
	 * Load all terms of a vocabulary from database.
	 * 
	 * @return Term[]
	 */
	public function loadAll() {
		$vocabulary = $this->vocabulary();
		$vid = $vocabulary->vid;

		static $cache;
		if (!is_numeric($vid)) {
			$vid = TermVocabulary::model()->getIdByMName($vid);
		}
		if (!isset($cache[$vid])) {
			$res =  $this->findAllByAttributes(array('vid' => $vid));
			$cache[$vid] = Utils::arrayColumns($res, null, 'tid');
		}
		return $cache[$vid];
	}
	
	/**
	 * Build a tree structure from a list of term.
	 * 
	 * @return array
	 */
	public function buildTree() {
		$vocabulary = $this->vocabulary();
		$vid = $vocabulary->vid;
		
		if (!is_numeric($vid)) {
			$vid = TermVocabulary::model()->getIdByMName($vid);
		}
		static $treeCache;
		if (isset($treeCache[$vid])) {
			return $treeCache[$vid];
		}
		
		$terms = $this->loadAll($vid);
		$tids = Utils::arrayColumns($terms, 'tid', null);
		
		foreach ($terms as &$tmpTerm) {
			$tmpTerm = $tmpTerm->toStdClass();
		}
		//print_r($terms);
		$backups = $terms;
		$childrenMaps = TermHierarchy::model()->getChildren($tids);
		
		foreach ($childrenMaps as $tid => $children) {
			$term = $backups[$tid];
			foreach ($children as $child) {
				
				if (!isset($term->children)) {
					$term->hasChildren = true;
					$term->children = array($backups[$child]);
				}else {
					$term->children[] = $backups[$child];
				}
				unset($terms[$child]);
			}
		}
		return $treeCache[$vid] = $terms;
	}
	
	/**
	 * Walk throuth a term tree.
	 * 
	 * @param array $tree
	 * @param callable $callback
	 */
	public function termTreeWalk($tree, $callback) {
		$tree2 = $tree;
		$this->termTreeWalkHelper($tree2, $callback);
		return $tree2;
	}
	
	protected function termTreeWalkHelper(&$tree, $callback) {
		foreach ($tree as $key => &$child) {
			if (!$child instanceof stdClass) {
				continue;
			}
			if ($child->hasChildren) {
				$this->termTreeWalkHelper($child->children, $callback);
			}
			if (is_callable($callback)) {
				if ($n = call_user_func($callback, $child)) {
					$child = $n;
				}
			}
		}
	}
	
	/**
	 * Check if a term is exist.
	 * 
	 * @param integer $tid
	 */
	public function checkExist($tid) {
		$vid = $this->vocabulary()->vid;
		$list = $this->loadAll($vid);
		return isset($list[$tid]);
	}
	
	/**
	 * Add a child term for current term.
	 * 
	 * @param integer|Term $child
	 * @return boolean
	 */
	public function addChild($child) {
		if ($child instanceof self){
			$child = $child->tid;
		}
		return TermHierarchy::add($child, $this->tid);
	}
	
	/**
	 * Remove a child term for current term.
	 *
	 * @param integer|Term $child
	 * @return boolean
	 */
	public function removeChild($child) {
		if ($child instanceof self) {
			$child = $child->tid;
		}
		if (TermHierarchy::remove($child, $this->tid)) {
			static::model()->deleteByPk($child);
			return true;
		}
		return false;
	}
	
	public function toStdClass() {
		$ret = (object)$this->getAttributes();
		$ret->hasChildren = false;
		return $ret;
	}
	
	/**
	 * Attach the term to a entity.
	 *
	 * @param integer $entityId
	 * @param string $entityType
	 */
	public function attachTo($entityId, $entityType) {
		return (boolean)TermEntity::add($this->tid, $entityId, $entityType);
	}
	
	/**
	 * Remove the attach from entity.
	 * 
	 * @param integer $entityId
	 * @param string  $entityType
	 * @return boolean
	 */
	public function unattach($entityId, $entityType) {
		return (boolean)TermEntity::remove($this->tid, $entityId, $entityType);
	}
	
	/**
	 * Get the depth level of the term.
	 * 
	 * @return integer The integer depth level, start from 1.
	 * @todo Performance issues on a large number of levels.
	 */
	public function getDepth() {
		$level = 1;
		$tid = $this->tid;
		
		do {
			$model = TermHierarchy::model()->findByAttributes(array('tid' => $tid));
		}while ($model && ($tid = $model->parent) != 0 && $level ++);
		
		return $level;
	}
	
	/**
	 * Get all parent of the Term.
	 * 
	 * @return Term[] Empty array if no parent.
	 */
	public function parents() {
		$ptids = TermHierarchy::fetchParents($this->tid);
		$ret = array();
		if (!empty($ptids)) {
			$ret = static::loadByIds($ptids);
		}
		return $ret;
	}
	
	/**
	 * Get all children of the Term.
	 * 
	 * @param boolean $onlyIds
	 * @return Term[] terms indexed by tid
	 */
	public function children($onlyIds = false) {
		return self::fetchChildren($this->tid, $this->vid, $onlyIds);
	}
	
	/**
	 * Return whether the term has children.
	 * 
	 * @return boolean
	 */
	public function getHasChildren() {
		return TermHierarchy::model()->hasChildren($this->tid, $this->vid);
	}
	
	/**
	 * Fetch all child of a term.
	 *
	 * @param integer $termId
	 * @param integer $vid
	 * @param boolean $onlyIds
	 * @return Term[]|integer[]
	 */
	public static function fetchChildren($termId, $vid = null, $onlyIds = false) {
		if ($vid === null) {
			$vid = self::model()->getVocabularyId();
		}
		$children = TermHierarchy::model()->getChildren($termId, $vid);
		if ($onlyIds) {
			return $children;
		}
		$ret = array();
		foreach ($children as $child) {
			$term = self::model()->findByPk($child);
			if ($term) {
				$ret[$term->tid] = $term;
			}
		}
		return $ret;
	}
	
	public static function fetchToplevels($vid = null) {
		return static::fetchChildren(0, $vid);
	}
	
	/**
	 * Get the number of entity attached the tag.
	 * 
	 * @param string $entityType default null for all entityTypes.
	 * @return integer
	 */
	public function getNumOfAttached($entityType = null) {
		$criteria = new CDbCriteria();
		$criteria->addCondition('tid=' . $this->tid);
		if ($entityType !== null) {
			$criteria->addCondition('entity_type=' . $entityType);
		}
		return TermEntity::model()->count($criteria);
	}
	
	/**
	 * Load term by ids.
	 * 
	 * @param array   $tids
	 * @param boolean $indexedById
	 * @return Term[]
	 */
	public static function loadByIds($tids, $indexedById = false) {
		$criteria = new CDbCriteria();
		$criteria->addInCondition('tid', $tids);
		$terms = static::model()->findAll($criteria);
		if ($indexedById) {
			$terms = Utils::arrayColumns($terms, null, 'tid');
		}
		return $terms;
	}
	
	/**
	 * Load a term for database by its tid.
	 * 
	 * @param mixed $tid tid or array of tid
	 * @param boolean $indexedByTid
	 * @return Term|Term[]
	 */
	public static function load($tid, $indexedByTid = false) {
		if (is_array($tid)) {
			return static::model()->findAllByPk($tid, $indexedByTid ? array('index' => 'tid') : '');
		}else {
			return static::model()->findByPk($tid);
		}
	}
	
	
	/**
	 * Fetch all terms attached to specified entity.
	 * 
	 * @param integer $entityId
	 * @param string  $entityType
	 * @return CArrayDataProvider
	 */
	public static function fetchProviderByEntity($entityId, $entityType) {
		$terms = TermEntity::getAttachedTerms($entidyId, $entityType);
		return new CArrayDataProvider($terms);
	}
	
	
	
	public function getEntityProvider($entityType, $pageSize = 10) {
		
	}
	
	protected function beforeSave() {
		return parent::beforeSave();
	}
	
	protected function afterSave() {
		return parent::afterSave();
	}
	
	protected function afterDelete() {
		//TODO remove hierarchy relations
		return parent::afterDelete();
	}
}
