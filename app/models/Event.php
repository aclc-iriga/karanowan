<?php

require_once 'App.php';

class Event extends App
{
    // table
    protected $table = 'events';

    // properties
    protected $id;
    protected $category_id;
    protected $slug;
    protected $title;


    /***************************************************************************
     * Event constructor
     *
     * @param int $id
     */
    public function __construct($id = 0)
    {
        parent::__construct();

        // get other info
        if($id > 0) {
            $stmt = $this->conn->prepare("SELECT * FROM $this->table WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $this->id = $row['id'];
                $this->category_id = $row['category_id'];
                $this->slug = $row['slug'];
                $this->title = $row['title'];
            }
        }
    }


    /***************************************************************************
     * Execute find
     *
     * @param $stmt
     * @return Event|false
     */
    private static function executeFind($stmt)
    {
        $stmt->execute();
        $result = $stmt->get_result();
        if($row = $result->fetch_assoc())
            return new Event($row['id']);
        else
            return false;
    }


    /***************************************************************************
     * Find event by id
     *
     * @param int $id
     * @return Event|boolean
     */
    public static function findById($id)
    {
        $event = new Event();
        $stmt = $event->conn->prepare("SELECT id FROM $event->table WHERE id = ?");
        $stmt->bind_param("i", $id);
        return self::executeFind($stmt);
    }


    /***************************************************************************
     * Find event by slug
     *
     * @param string $slug
     * @return Event|boolean
     */
    public static function findBySlug($slug)
    {
        $event = new Event();
        $stmt = $event->conn->prepare("SELECT id FROM $event->table WHERE slug = ?");
        $stmt->bind_param("s", $slug);
        return self::executeFind($stmt);
    }


    /***************************************************************************
     * Convert event object to array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'id'          => $this->id,
            'category_id' => $this->category_id,
            'slug'        => $this->slug,
            'title'       => $this->title
        ];
    }


    /***************************************************************************
     * Get all events as array of objects
     *
     * @param int $category_id
     * @return Event[]
     */
    public static function all($category_id = 0)
    {
        $event = new Event();
        $sql = "SELECT id FROM $event->table ";
        if($category_id > 0)
            $sql .= "WHERE category_id = ? ";
        $sql .= "ORDER BY id";
        $stmt = $event->conn->prepare($sql);
        if($category_id > 0)
            $stmt->bind_param("i", $category_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $events = [];
        while($row = $result->fetch_assoc()) {
            $events[] = new Event($row['id']);
        }
        return $events;
    }


    /***************************************************************************
     * Get all events as array of arrays
     *
     * @param int $category_id
     * @return array
     */
    public static function rows($category_id = 0)
    {
        $events = [];
        foreach(self::all($category_id) as $event) {
            $events[] = $event->toArray();
        }
        return $events;
    }


    /***************************************************************************
     * Check if event id exists
     *
     * @param $id
     * @return bool
     */
    public static function exists($id)
    {
        if(!$id)
            return false;

        return (self::findById($id) != false);
    }


    /***************************************************************************
     * Check if event slug exists
     *
     * @param string $slug
     * @param int $id
     * @return bool
     */
    public static function slugExists($slug, $id = 0)
    {
        $event = new Event();
        $stmt = $event->conn->prepare("SELECT id FROM $event->table WHERE slug = ? AND id != ?");
        $stmt->bind_param("si", $slug, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return ($result->num_rows > 0);
    }


    /***************************************************************************
     * Insert event
     *
     * @return void
     */
    public function insert()
    {
        // check id
        if(self::exists($this->id))
            App::returnError('HTTP/1.1 500', 'Insert Error: event [id = ' . $this->id . '] already exists.');

        // check category_id
        require_once 'Category.php';
        if(!Category::exists($this->category_id))
            App::returnError('HTTP/1.1 500', 'Insert Error: category [id = ' . $this->category_id . '] does not exist.');

        // check slug
        if(self::slugExists($this->slug))
            App::returnError('HTTP/1.1 500', 'Insert Error: event [slug = ' . $this->slug . '] already exists.');

        // proceed with insert
        $stmt = $this->conn->prepare("INSERT INTO $this->table(category_id, slug, title) VALUES(?, ?, ?)");
        $stmt->bind_param("iss", $this->category_id, $this->slug, $this->title);
        $stmt->execute();
        $this->id = $this->conn->insert_id;
    }


    /***************************************************************************
     * Update event
     *
     * @return void
     */
    public function update()
    {
        // check id
        if(!self::exists($this->id))
            App::returnError('HTTP/1.1 500', 'Update Error: event [id = ' . $this->id . '] does not exist.');

        // check category_id
        require_once 'Category.php';
        if(!Category::exists($this->category_id))
            App::returnError('HTTP/1.1 500', 'Update Error: category [id = ' . $this->category_id . '] does not exist.');

        // check slug
        if(self::slugExists($this->slug, $this->id))
            App::returnError('HTTP/1.1 500', 'Update Error: event [slug = ' . $this->slug . '] already exists.');

        // proceed with update
        $stmt = $this->conn->prepare("UPDATE $this->table SET category_id = ?, slug = ?, title = ? WHERE id = ?");
        $stmt->bind_param("issi", $this->category_id, $this->slug, $this->title, $this->id);
        $stmt->execute();
    }


    /***************************************************************************
     * Delete event
     *
     * @return void
     */
    public function delete()
    {
        // check id
        if(!self::exists($this->id))
            App::returnError('HTTP/1.1 500', 'Delete Error: event [id = ' . $this->id . '] does not exist.');

        // proceed with delete
        $stmt = $this->conn->prepare("DELETE FROM $this->table WHERE id = ?");
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
    }


    /***************************************************************************
     * Set category_id
     *
     * @param int $category_id
     * @return void
     */
    public function setCategoryId($category_id)
    {
        $this->category_id = $category_id;
    }


    /***************************************************************************
     * Set slug
     *
     * @param string $slug
     * @return void
     */
    public function setSlug($slug)
    {
        $this->slug = parent::generateSlug($slug);
    }


    /***************************************************************************
     * Set title
     *
     * @param string $title
     * @return void
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }


    /***************************************************************************
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /***************************************************************************
     * Get category_id
     *
     * @return int
     */
    public function getCategoryId()
    {
        return $this->category_id;
    }


    /***************************************************************************
     * Get slug
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }


    /***************************************************************************
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }


    /***************************************************************************
     * Get all criteria as array of objects
     *
     * @return Criterion[]
     */
    public function getAllCriteria()
    {
        require_once 'Criterion.php';
        return Criterion::all($this->id);
    }


    /***************************************************************************
     * Get all criteria as array of arrays
     *
     * @return array
     */
    public function getRowCriteria()
    {
        require_once 'Criterion.php';
        return Criterion::rows($this->id);
    }


    /***************************************************************************
     * Get parent category
     *
     * @return Category
     */
    public function getCategory()
    {
        require_once 'Category.php';
        return new Category($this->category_id);
    }
}
