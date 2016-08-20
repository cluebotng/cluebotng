all: copybins

bins:
	make -C src all

copybins: bins
	cp src/cluebotng src/create_ann src/create_bayes_db src/print_bayes_db .

clean:
	rm -f cluebotng create_ann create_bayes_db print_bayes_db
	make -C src clean

dataclean:
	rm -f data/* trialreport/*.txt trialreport/*.xml trialreport/*.png


bayes_db:
	@ ./util/train_bayes.sh

ann_train:
	@ ./util/train_ann.sh

trial:
	@ ./util/run_trial.sh

train:
	@ ./util/train_all.sh

trainandtrial:
	@ ./util/trainandtrial.sh

anntrainandtrial:
	@ ./util/anntrainandtrial.sh
