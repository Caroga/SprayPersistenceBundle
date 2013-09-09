<?php

namespace Spray\PersistenceBundle\Repository;

use Countable;
use Doctrine\ORM\EntityRepository as DoctrineEntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Iterator;
use Spray\PersistenceBundle\EntityFilter\EntityFilterInterface;
use Spray\PersistenceBundle\EntityFilter\FilterAggregateInterface;
use Spray\PersistenceBundle\EntityFilter\FilterManager;

/**
 * A filterable entity repository
 * 
 * Instead of just passing data around, this Repository has a state that you can
 * alter with the use of EntityFilters.
 *
 * @author MHWK
 */
class FilterableEntityRepository extends DoctrineEntityRepository
    implements FilterableRepositoryInterface, Countable, Iterator
{
    /**
     * @var boolean
     */
    private $hydrate = true;
    
    /**
     * @var null|array
     */
    private $collection;
    
    /**
     * @var integer
     */
    private $index = 0;
    
    /**
     * @var FilterManager
     */
    private $filterManager;
    
    /**
     * Adds a call to configure()
     * 
     * @inheritdoc
     */
    public function __construct($em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
        $this->configure();
    }
    
    /**
     * Make sure everything is properly cloned
     * 
     * @return void
     */
    public function __clone()
    {
        if (null !== $this->filterManager) {
            $this->filterManager = clone $this->filterManager;
        }
    }
    
    /**
     * Override to implement configuration
     * 
     * @return void
     */
    protected function configure()
    {
        
    }
    
    /**
     * Enable hydration (default)
     * 
     * @return void
     */
    public function enableHydration()
    {
        $this->hydrate = true;
    }
    
    /**
     * Disable hydration
     * 
     * @return void
     */
    public function disableHydration()
    {
        $this->hydrate = false;
    }
    
    /**
     * Test wether hydration is enabled
     * 
     * @return boolean
     */
    public function isHydrationDisabled()
    {
        return false === $this->hydrate;
    }


    /**
     * Load data if not already and return it. Important to call by reference
     * to avoid array copies
     * 
     * @return array
     */
    private function &getCollection()
    {
        if (null === $this->collection) {
            $qb = $this->createAndFilterQueryBuilder($this->getEntityAlias());
            if ($this->isHydrationDisabled()) {
                $this->collection = $qb->getQuery()->getScalarResult();
            } else {
                $this->collection = $qb->getQuery()->getResult();
            }
        }
        return $this->collection;
    }
    
    /**
     * Set the filter chain
     * 
     * @param FilterManager $filterManager
     */
    public function setFilterManager(FilterAggregateInterface $filterManager)
    {
        $this->filterManager = $filterManager;
    }
    
    /**
     * Get the filter chain
     * 
     * @return FilterManager
     */
    public function getFilterManager()
    {
        if (null === $this->filterManager) {
            $this->setFilterManager(new FilterManager());
        }
        return $this->filterManager;
    }
    
    /**
     * @inheritdoc
     */
    public function count()
    {
        if (null === $this->collection) {
            // This obviously needs to be fixed
            return count($this->getCollection());
        }
        return count($this->collection);
    }

    /**
     * @inheritdoc
     */
    public function current()
    {
        if (null === $this->collection) {
            $qb = $this->createAndFilterQueryBuilder($this->getEntityAlias());
            $qb->setMaxResults(1);
            return $qb->getQuery()->getSingleResult();
        }
        return $this->collection[$this->index];
    }

    /**
     * @inheritdoc
     */
    public function key()
    {
        return $this->index;
    }

    /**
     * @inheritdoc
     */
    public function next()
    {
        $this->index++;
    }

    /**
     * Loop detected: load data
     * 
     * @inheritdoc
     */
    public function rewind()
    {
        $this->getCollection();
        $this->index = 0;
    }

    /**
     * @inheritdoc
     */
    public function valid()
    {
        $collection = &$this->getCollection();
        return isset($collection[$this->index]);
    }
    
    /**
     * @inheritdoc
     */
    public function filter(EntityFilterInterface $filter)
    {
        $this->collection = null;
        $this->getFilterManager()->addFilter($filter);
    }
    
    /**
     * Filter passed $qb
     * 
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    public function filterQueryBuilder(QueryBuilder $qb)
    {
        $this->getFilterManager()->filter($qb);
        return $qb;
    }
    
    /**
     * Create a new QueryBuilder and filter it using attached filters
     * 
     * @param string $alias
     * @return QueryBuilder
     */
    public function createAndFilterQueryBuilder($alias)
    {
        return $this->filterQueryBuilder($this->createQueryBuilder($alias));
    }
    
    /**
     * Returns all uppercase letters of the entity name without the
     * namespace lowercased
     * 
     * @return string
     */
    protected function getEntityAlias()
    {
        $entityName = $this->getEntityName();
        if (false !== strpos($entityName, '\\')) {
            $entityName = substr(
                $entityName,
                strrpos($this->getEntityName(), '\\') + 1
            );
        }

        $matches = array();
        preg_match_all(
            '/[A-Z]/',
            $entityName,
            $matches
        );
        return strtolower(implode('', $matches[0]));
    }
}