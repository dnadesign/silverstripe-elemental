import Injector from 'lib/Injector';
import Element from 'components/ElementEditor/Element';
import ElementEditor from 'components/ElementEditor/ElementEditor';
import ElementList from 'components/ElementEditor/ElementList';
import Toolbar from 'components/ElementEditor/Toolbar';
import AddNewButton from 'components/ElementEditor/AddNewButton';
import Header from 'components/ElementEditor/Header';
import Content from 'components/ElementEditor/Content';

export default () => {
  Injector.component.registerMany({
    ElementEditor,
    ElementToolbar: Toolbar,
    ElementAddNewButton: AddNewButton,
    ElementList,
    Element,
    ElementHeader: Header,
    ElementContent: Content,
  });
};
