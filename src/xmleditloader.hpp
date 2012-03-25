#ifndef _XMLEDITLOADER_HPP
#define _XMLEDITLOADER_HPP


#include <deque>
#include <string>
#include <map>
#include <list>
#include <iostream>
#include <vector>
#include "framework.hpp"
#include <expat.h>
#include <fstream>
#include <sstream>
#include <stdlib.h>


namespace WPCluebot {


class ExpatPPHandler {
	public:
		virtual void elementStartHandler(const std::string & name, std::map<std::string,std::string> & attrs) {}
		virtual void elementEndHandler(const std::string & name) {}
		virtual void elementDataHandler(const std::string & data) {}
		virtual ~ExpatPPHandler() {}
};

class ExpatPP {
	public:
		ExpatPP() : exp_parser(NULL), handler(NULL) {}
		ExpatPP(ExpatPPHandler & hand) : exp_parser(NULL), handler(NULL) {
			setHandler(hand);
		}
		~ExpatPP() {
			if(exp_parser) XML_ParserFree(exp_parser);
		}
	
		void setHandler(ExpatPPHandler & hand) {
			handler = &hand;
		}
		
		void startParsing() {
			if(exp_parser) XML_ParserFree(exp_parser);
			exp_parser = XML_ParserCreate(NULL);
			if(!exp_parser) throw std::bad_alloc();
			XML_SetUserData(exp_parser, reinterpret_cast<void *>(this));
			XML_SetElementHandler(exp_parser, exp_StartElementHandler, exp_EndElementHandler);
			XML_SetCharacterDataHandler(exp_parser, exp_CharacterDataHandler);
		}
		void submitData(const char * data, unsigned int len) {
			if(XML_Parse(exp_parser, data, (int)len, 0) == 0) {
				std::stringstream errstr;
				errstr << "Error parsing XML on line " << XML_GetCurrentLineNumber(exp_parser) << ": " << XML_ErrorString(XML_GetErrorCode(exp_parser));
				throw std::runtime_error(errstr.str().c_str());
			}
		}
		void endParsing() {
			if(XML_Parse(exp_parser, NULL, 0, 1) == 0) {
				std::stringstream errstr;
				errstr << "Error parsing XML on line " << XML_GetCurrentLineNumber(exp_parser) << ": " << XML_ErrorString(XML_GetErrorCode(exp_parser));
				throw std::runtime_error(errstr.str().c_str());
			}
			if(exp_parser) XML_ParserFree(exp_parser);
			exp_parser = NULL;
		}
		
		void parseFile(const std::string & fname) {
			std::ifstream f;
			f.exceptions(std::ifstream::failbit | std::ifstream::badbit);
			f.open(fname.c_str(), std::ifstream::in);
			f.exceptions(std::ifstream::badbit);
			startParsing();
			char buf[1024];
			try {
				while(!f.fail() && !f.eof()) {
					f.read(buf, sizeof(buf));
					submitData(buf, f.gcount());
				}
			} catch(...) {
				f.close();
				std::cout << "Exception around line " << XML_GetCurrentLineNumber(exp_parser) << "\n";
				endParsing();
				throw;
			}
			f.close();
			endParsing();
		}
		
		bool parseFile_more() {
			char buf[1024];
			if(incfile_finished) return false;
			if(incfile_f.fail() || incfile_f.eof()) {
				if(file_size) file_pos = file_size;
				endParsing();
				incfile_f.close();
				incfile_finished = true;
				return true;
			}
			if(file_size) file_pos = incfile_f.tellg();
			incfile_f.read(buf, sizeof(buf));
			submitData(buf, incfile_f.gcount());
			return true;
		}
		
		void parseFile_start(const std::string & fname) {
			incfile_f.exceptions(std::ifstream::failbit | std::ifstream::badbit);
			incfile_f.open(fname.c_str(), std::ifstream::in);
			file_pos = 0;
			file_size = 0;
			incfile_f.exceptions();
			incfile_f.seekg(0, std::ifstream::end);
			if(!incfile_f.bad() && !incfile_f.fail()) {
				long long int siz = incfile_f.tellg();
				if(siz > 0) file_size = siz;
			}
			incfile_f.seekg(0, std::ifstream::beg);
			incfile_f.exceptions(std::ifstream::badbit);
			startParsing();
			incfile_finished = false;
		}
		
		unsigned long long int parseFile_size() { return file_size; }
		unsigned long long int parseFile_pos() { return file_pos; }
		
		static void makeStrLCase(std::string & str) {
			for(int i = 0; i < str.size(); ++i) if(str[i] >= 'A' && str[i] <= 'Z') str[i] = str[i] + ('a' - 'A');
		}
		
	private:
		XML_Parser exp_parser;
		ExpatPPHandler * handler;
		
		std::ifstream incfile_f;
		bool incfile_finished;
		
		unsigned long long int file_size;
		unsigned long long int file_pos;
	
		static void exp_StartElementHandler(void * userdata, const XML_Char *name, const XML_Char **attrs) {
			ExpatPPHandler * hand = (reinterpret_cast<ExpatPP *>(userdata))->handler;
			if(!hand) return;
			std::string strname((char *)name);
			std::map<std::string,std::string> attrmap;
			if(attrs) while(*attrs) {
				std::string attrnamestr((char *)(*(attrs++)));
				std::string attrvalstr((char *)(*(attrs++)));
				makeStrLCase(attrnamestr);
				attrmap[attrnamestr] = attrvalstr;
			}
			makeStrLCase(strname);
			hand->elementStartHandler(strname, attrmap);
		}
		static void exp_EndElementHandler(void * userdata, const XML_Char *name) {
			ExpatPPHandler * hand = (reinterpret_cast<ExpatPP *>(userdata))->handler;
			if(!hand) return;
			std::string strname((char *)name);
			makeStrLCase(strname);
			hand->elementEndHandler(strname);
		}
		static void exp_CharacterDataHandler(void * userdata, const XML_Char *data, int datalen) {
			ExpatPPHandler * hand = (reinterpret_cast<ExpatPP *>(userdata))->handler;
			if(!hand) return;
			char * cdata = (char *)data;
			char * ndata = new char[datalen];
			int ndatalen = 0;
			for(int i = 0; i < datalen; ++i) if(cdata[i] != '\r') ndata[ndatalen++] = cdata[i];
			std::string strdat(ndata, ndatalen);
			delete[] ndata;
			hand->elementDataHandler(strdat);
		}
};

class XMLEditParseHandler : public ExpatPPHandler {
	public:
		bool gotEndTag;
	
		Edit nextEdit() {
			Edit ed = complete_edits.front();
			complete_edits.pop_front();
			return ed;
		}
		unsigned int numCompleteEdits() {
			return complete_edits.size();
		}
		void reset() {
			tag_stack.clear();
			cur_buf.clear();
			current_edit = Edit();
			complete_edits.clear();
		}
		
		XMLEditParseHandler(libconfig::Setting & cfg) : gotEndTag(false) {
			for(int i = 0; i < cfg["proptypes"].getLength(); ++i) {
				std::string propname = cfg["proptypes"][i].getName();
				std::string proptype = cfg["proptypes"][i];
				if(proptype == "string") proptable[propname] = PROPTYPE_STRING; else
				if(proptype == "int") proptable[propname] = PROPTYPE_INT; else
				if(proptype == "float") proptable[propname] = PROPTYPE_FLOAT; else
				if(proptype == "bool") proptable[propname] = PROPTYPE_BOOL; else
				if(proptype == "datetime") proptable[propname] = PROPTYPE_INT; else
				throw std::runtime_error("Unknown property type.");
			}
		}
	
		void elementStartHandler(const std::string & name, std::map<std::string,std::string> & attrs) {
			cur_buf.clear();
			if(name == "wpeditset") return;
			if(name == "wpedit") {
				current_edit = Edit();
				tag_stack.clear();
				return;
			}
			tag_stack.push_back(name);
		}
		void elementEndHandler(const std::string & name) {
			if(name == "wpeditset") {
				gotEndTag = true;
				return;
			}
			if(name == "wpedit") {
				complete_edits.push_back(current_edit);
				return;
			}
			if(tag_stack.size()) tag_stack.pop_back();
			if(name != "current" && name != "previous" && name != "common") {
				std::list<std::string>::iterator stack_it = tag_stack.begin();
				bool is_common = false;
				if(stack_it != tag_stack.end()) if(*stack_it == "common") {
					is_common = true;
					stack_it++;
				}
				std::string propname;
				for(; stack_it != tag_stack.end(); ++stack_it) propname += *stack_it + "_";
				if(is_common) {
					addEditProp(name, std::string("current_") + propname + name, cur_buf);
					addEditProp(name, std::string("previous_") + propname + name, cur_buf);
				} else {
					addEditProp(name, propname + name, cur_buf);
				}
			}
		}
		void elementDataHandler(const std::string & data) {
			cur_buf += data;
		}
	
	private:
		enum XMLEditPropType { PROPTYPE_STRING, PROPTYPE_INT, PROPTYPE_FLOAT, PROPTYPE_BOOL, PROPTYPE_DATETIME };
		
		std::list<std::string> tag_stack;
		
		std::string cur_buf;
		Edit current_edit;
		std::deque<Edit> complete_edits;
		
		typedef XMLEditPropType PropType;
		std::map<std::string, PropType> proptable;
		
		void addEditProp(const std::string & short_name, const std::string & full_name, const std::string & strval) {
			PropType t;
			if(proptable.count(full_name) == 1) t = proptable[full_name]; else
			if(proptable.count(short_name) == 1) t = proptable[short_name]; else
			t = PROPTYPE_STRING;
			bool b = false;
			std::string lcval;
			switch(t) {
				case PROPTYPE_STRING:
					current_edit.setProp<std::string>(full_name, strval);
					break;
				case PROPTYPE_INT:
				case PROPTYPE_DATETIME:
					current_edit.setProp<int>(full_name, atoi(strval.c_str()));
					break;
				case PROPTYPE_FLOAT:
					current_edit.setProp<float>(full_name, atof(strval.c_str()));
					break;
				case PROPTYPE_BOOL:
					lcval = strval;
					ExpatPP::makeStrLCase(lcval);
					if(lcval == "t" || lcval == "true" || lcval == "y" || lcval == "yes" || lcval == "1") b = true;
					current_edit.setProp<bool>(full_name, b);
					break;
			}
		}
};

class XMLEditParser {
	public:
		XMLEditParser(libconfig::Setting & cfg) : handler(cfg) {
			parser.setHandler(handler);
		}
		unsigned int availableEdits() {
			return handler.numCompleteEdits();
		}
		Edit nextEdit() {
			return handler.nextEdit();
		}
		void parseFile(const std::string & fname);
		
		void parseFile_start(const std::string & fname) {
			parser.parseFile_start(fname);
		}
		bool parseFile_more() {
			return parser.parseFile_more();
		}
		unsigned long long int parseFile_size() { return parser.parseFile_size(); }
		unsigned long long int parseFile_pos() { return parser.parseFile_pos(); }
		
		void startParsing() { parser.startParsing(); }
		void submitData(const char * data, unsigned int len) { parser.submitData(data, len); }
		void endParsing() { parser.endParsing(); }
		
		bool gotEndTag() { return handler.gotEndTag; }
		
	private:
		XMLEditParseHandler handler;
		ExpatPP parser;	
};


}


#endif

