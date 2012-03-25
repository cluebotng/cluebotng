package main

import (
	"mysql"
	"http"
	"fmt"
	"strconv"
	"strings"
	"os"
	"bytes"
	"flag"
)

type pipelineStep struct {
	input chan *pipelinePackage
	quit chan int
}

type pipelinePackage struct {
	id int
	page uint
	user string
	comment string
	nextUser string
	nextId uint
	nextComment string
	delta int
	origSize int
}

func getMysql() (db *mysql.MySQL) {
	db = mysql.New()
	db.Connect("sql-s1", "cobi", MySQLPassword, "enwiki_p")
	return db
}

func filter(needDb bool, shard int, decision func(id *pipelinePackage, db *mysql.MySQL) bool, next pipelineStep) pipelineStep {
	input := make(chan *pipelinePackage, 100)
	nquit := next.quit
	output := next.input
	var db *mysql.MySQL
	if needDb {
		db = getMysql()
	} else {
		db = nil
	}
	for iter := 0 ; iter < shard ; iter++ {
		quit := make(chan int)
		go func(thisQuit, nextQuit chan int) {
			for {
				select {
				case <-nextQuit:
					thisQuit <- 1
					return
				case id := <-input:
					if id == nil {
						output <- nil
					} else if decision(id, db) {
						output <- id
					}
				}
			}
		}(quit, nquit)
		nquit = quit
	}
	step := pipelineStep{input, nquit}
	return step
}

func branch(needDb bool, shard int, decision func(id *pipelinePackage, db *mysql.MySQL) bool, branch1, branch2 pipelineStep) pipelineStep {
	input := make(chan *pipelinePackage, 100)
	nquit := branch1.quit
	output1 := branch1.input
	output2 := branch2.input
	var db *mysql.MySQL
	if needDb {
		db = getMysql()
	} else {
		db = nil
	}
	for iter := 0 ; iter < shard ; iter++ {
		quit := make(chan int)
		go func(thisQuit, nextQuit chan int) {
			for {
				select {
				case <-nextQuit:
					if nextQuit == branch1.quit {
						<-branch2.quit
					}
					thisQuit <- 1
					return
				case id := <-input:
					if id == nil {
						output1 <- nil
						output2 <- nil
					} else if decision(id, db) {
						output1 <- id
					} else {
						output2 <- id
					}
				}
			}
		}(quit, nquit)
		nquit = quit
	}
	step := pipelineStep{input, nquit}
	return step
}

func rangeGenerator(start, end int, next pipelineStep) {
	for iter := start ; iter < end ; iter++ {
		pkg := new(pipelinePackage)
		pkg.id = iter
		next.input <- pkg
	}
	next.input <- nil
	<-next.quit
}

func outputSink(format string) pipelineStep {
	input := make(chan *pipelinePackage)
	quit := make(chan int)
	step := pipelineStep{input, quit}
	go func() {
		for {
			id := <-input
			if id == nil {
				quit <- 1
				return
			} else {
				fmt.Printf(format, id.id)
			}
		}
	}()
	return step
}

func getURL(url string) (data string, error os.Error) {
	resp, _, err := http.Get(url)
	if err != nil {
		return "", err
	}
	respData := make([]byte, 2097152)
	n, _ := resp.Body.Read(respData)
	resp.Body.Close()
	data = bytes.NewBuffer(respData[0:n]).String()
	return data, nil
}

var isDebug *bool = flag.Bool("debug", false, "Enable debugging")
var start *int = flag.Int("start", 341000000, "Starting ID")
var end *int = flag.Int("end", 342000000, "Ending ID")

func debug(name string, id int, ret bool) bool {
	if !*isDebug {
		return ret
	}
	fmt.Printf("#DEBUG ID=%d STAGE=%s RETURN=%t\n", id, name, ret)
	return ret
}

func main() {
	flag.Parse()
	rangeGenerator(*start, *end,
	filter(true, 3, func(id *pipelinePackage, db *mysql.MySQL) bool {
		//If edit is in ns0, return true.
		res, err := db.Query("SELECT `page_namespace`, `page_id`, `rev_user_text`, `rev_comment` FROM `revision` JOIN `page` ON `rev_page` = `page_id` WHERE `rev_id` = " + strconv.Itoa(id.id))
		if err != nil {
			fmt.Printf("1Error: %v\n", err)
			return false
		}
		row := res.FetchRow()
		if row == nil {
			return debug("f_ns0_0", id.id, false)
		}
		if row[0] == 0 {
			id.page, _ = row[1].(uint)
			id.user, _ = row[2].(string)
			id.comment, _ = row[3].(string)
			return debug("f_ns0_1", id.id, true)
		}
		return debug("f_ns0_2", id.id, false)
	},
	branch(true, 3, func(id *pipelinePackage, db *mysql.MySQL) bool {
		//If edit was reverted, return true.
		res, err := db.Query("SELECT `rev_id`, `rev_user_text`, `rev_comment` FROM `revision` WHERE `rev_page` = " + strconv.Uitoa(id.page) + " AND `rev_id` > " + strconv.Itoa(id.id) + " ORDER BY `rev_id` ASC LIMIT 1")
		if err != nil {
			fmt.Printf("2Error: %v\n", err)
			return false
		}
		row := res.FetchRow()
		if row == nil {
			return debug("br_pv_0", id.id, false)
		}
		id.nextId, _ = row[0].(uint)
		id.nextUser, _ = row[1].(string)
		id.nextComment, _ = row[2].(string)
		if (strings.Contains(id.nextComment, "Revert") || strings.Contains(id.nextComment, "Undid")) && (strings.Contains(id.nextComment, id.user) || strings.Contains(id.nextComment, strconv.Itoa(id.id))) {
			if !strings.Contains(id.nextUser, "Bot") {
				return debug("br_pv_1", id.id, true)
			}
		}
		return debug("br_pv_2", id.id, false)
	},
	filter(true, 3, func(id *pipelinePackage, db *mysql.MySQL) bool {
		//If user was warned, return true.
		res, err := db.Query("SELECT `rev_id` FROM `revision` JOIN `page` ON `page_id` = `rev_page` WHERE `page_namespace` = 3 AND `page_title` = '" + db.Escape(strings.Replace(id.user, " ", "_", -1)) + "' AND `rev_user_text` = '" + db.Escape(id.nextUser) + "' AND (`rev_comment` LIKE '%warn%' OR `rev_comment` LIKE '%Warn%' OR `rev_comment` LIKE '%WARN%' OR `rev_comment` LIKE 'General note: Nonconstructive%') LIMIT 1")
		if err != nil {
			fmt.Printf("3Error: %v\n", err)
			return false
		}
		row := res.FetchRow()
		if row == nil {
			return debug("fv_w?_0", id.id, false)
		}
		return debug("fv_w?_1", id.id, true)
	},
	filter(false, 10, func(id *pipelinePackage, db *mysql.MySQL) bool {
		//If user still has warning, return true.
		data, err := getURL("http://en.wikipedia.org/w/index.php?action=raw&title=User_talk:" + http.URLEscape(id.user))
		if err != nil {
			fmt.Printf("3.5Error: %v\n", err)
			return false
		}
		if strings.Contains(data, "<!-- Template:") {
			if strings.Contains(data, id.nextUser) {
				return debug("fv_tw_0", id.id, true)
			}
		}
		return debug("fv_tw_1", id.id, false)
	},
	filter(true, 3, func(id *pipelinePackage, db *mysql.MySQL) bool {
		//If warner has >300 edits, return true.
		res, err := db.Query("SELECT `user_editcount` FROM `user` WHERE `user_name` = '" + db.Escape(id.nextUser) + "'")
		if err != nil {
			fmt.Printf("4Error: %v\n", err)
			return false
		}
		row := res.FetchRow()
		if row == nil {
			return debug("fv_w300_0", id.id, false)
		}
		editCount, worked := row[0].(int)
		if !worked {
			fmt.Printf("Error-2: %v\n", row[0])
		}
		if editCount > 300 {
			return debug("fv_w300_1", id.id, true)
		}
		return debug("fv_w300_2", id.id, false)
	},
	outputSink("%d V\n")))),

	filter(false, 20, func(id *pipelinePackage, db *mysql.MySQL) bool {
		//If user has no warnings on talk page, return true.
		data, err := getURL("http://en.wikipedia.org/w/index.php?action=raw&title=User_talk:" + http.URLEscape(id.user))
		if err != nil {
			fmt.Printf("5Error: %v\n", err)
			return false
		}
		if err != nil {
			return debug("f_nw_0", id.id, false)
		}
		if strings.Contains(data, "<!-- Template:uw-") {
			return debug("f_nw_1", id.id, false)
		}
		return debug("f_nw_2", id.id, true)
	},
	branch(true, 3, func(id *pipelinePackage, db *mysql.MySQL) bool {
		//If user has more than 100 edits, return true.
		res, err := db.Query("SELECT `user_editcount` FROM `user` WHERE `user_name` = '" + db.Escape(id.user) + "'")
		if err != nil {
			fmt.Printf("6Error: %v\n", err)
			return false
		}
		row := res.FetchRow()
		if row == nil {
			return debug("br_u100_0", id.id, false)
		}
		editCount, worked := row[0].(int)
		if !worked {
			fmt.Printf("Error0: %v\n", row[0])
		}
		if editCount > 100 {
			return debug("br_u100_1", id.id, true)
		}
		return debug("br_u100_2", id.id, false)
	},
	outputSink("%d C\n"),
	filter(true, 3, func(id *pipelinePackage, db *mysql.MySQL) bool {
		//If page has more than 8 revisions since, return true.
		res, err := db.Query("SELECT COUNT(*) FROM `revision` WHERE `rev_page` = " + strconv.Uitoa(id.page) + " AND `rev_id` > " + strconv.Itoa(id.id))
		if err != nil {
			fmt.Printf("7Error: %v\n", err)
			return false
		}
		row := res.FetchRow()
		if row == nil {
			return debug("f_8e_0", id.id, false)
		}
		count, _ := row[0].(uint)
		if count > 8 {
			return debug("f_8e_1", id.id, true)
		}
		return debug("f_8e_2", id.id, false)
	},
	filter(true, 3, func(id *pipelinePackage, db *mysql.MySQL) bool {
		//If any of the next 8 edits are reverts, return false.
		res, err := db.Query("SELECT COUNT(*) FROM (SELECT `rev_comment` FROM `revision` WHERE `rev_page` = " + strconv.Uitoa(id.page) + " AND `rev_id` > " + strconv.Itoa(id.id) + " LIMIT 8) AS `temp` WHERE `rev_comment` LIKE '%revert%' OR `rev_comment` LIKE 'Undid%'")
		if err != nil {
			fmt.Printf("8Error: %v\n", err)
			return false
		}
		row := res.FetchRow()
		if row == nil {
			return debug("f_8r_0", id.id, false)
		}
		count, worked := row[0].(int)
		if !worked {
			fmt.Printf("Error2: %v\n", row[0])
		}
		if count > 0 {
			return debug("f_8r_1", id.id, false)
		}
		return debug("f_8r_2", id.id, true)
	},
	filter(true, 3, func(id *pipelinePackage, db *mysql.MySQL) bool {
		//If edit resulted in increase in page length, and next 4 edits resulted in decrease, return false.
		res, err := db.Query("SELECT `a`.`rev_len` - `b`.`rev_len`, `b`.`rev_len` FROM `revision` AS `a` JOIN `revision` AS `b` ON `a`.`rev_parent_id` = `b`.`rev_id` WHERE `a`.`rev_id` >= " + strconv.Itoa(id.id) + " AND `a`.`rev_page` = " + strconv.Uitoa(id.page) + " ORDER BY `a`.`rev_id` ASC LIMIT 5")
		if err != nil {
			fmt.Printf("9Error: %v\n", err)
			return false
		}
		row := res.FetchRow()
		if row == nil {
			return debug("f_inc_0", id.id, false)
		}
		delta, worked0 := row[0].(int)
		id.delta = delta
		if !worked0 {
			fmt.Printf("Error3: %v\n", row[0])
		}
		origSize, worked1 := row[1].(int)
		id.origSize = origSize
		if !worked1 {
			fmt.Printf("Error4: %v\n", row[1])
		}
		if id.delta <= 0 {
			return debug("f_inc_1", id.id, true)
		}
		for {
			row := res.FetchRow()
			if row == nil {
				break
			}
			diff, _ := row[0].(int)
			if diff < 0 {
				return debug("f_inc_2", id.id, false)
			}
		}
		return debug("f_inc_3", id.id, true)
	},
	filter(true, 3, func(id *pipelinePackage, db *mysql.MySQL) bool {
		//If edit resulted in decrease in page length more than 500 bytes, and next 8 edits brought it back to within 10 bytes of original, return false.
		if id.delta >= 0 {
			return debug("f_dec_0", id.id, true)
		}

		res, err := db.Query("SELECT `rev_len` FROM `revision` WHERE `rev_id` > " + strconv.Itoa(id.id) + " AND `rev_page` = " + strconv.Uitoa(id.page) + " ORDER BY `a`.`rev_id` ASC LIMIT 8")
		if err != nil {
			fmt.Printf("0Error: %v\n", err)
			return false
		}
		for {
			row := res.FetchRow()
			if row == nil {
				break
			}
			length, worked := row[0].(int)
			if !worked {
				fmt.Printf("Error5: %v\n", row[0])
			}
			diff := length - id.origSize
			if diff < 10 && diff > -10 {
				return debug("f_dec_1", id.id, false)
			}
		}
		return debug("f_dec_2", id.id, true)
	},
	outputSink("%d C\n"))))))))))
}

// vim: ts=8:sw=8
